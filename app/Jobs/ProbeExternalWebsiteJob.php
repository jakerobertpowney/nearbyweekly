<?php

namespace App\Jobs;

use App\Models\ExternalWebsite;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProbeExternalWebsiteJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 2;

    public int $backoff = 120;

    /** Terms that indicate adult content — matched as whole hyphen/dot segments or segment prefixes */
    private const ADULT_TERMS = [
        'xxx', 'porn', 'escort', 'adult', 'sex', 'fetish',
        'erotic', 'nude', 'onlyfans', 'dating', 'hookup',
    ];

    /** TLDs restricted to trusted organisations — skip content-rating checks */
    private const TRUSTED_TLDS = ['.ac.uk', '.gov.uk', '.nhs.uk', '.police.uk', '.sch.uk'];

    /**
     * Common paths where event listings are found on UK event sites.
     * Tried in order when the homepage returns no Event JSON-LD.
     */
    private const COMMON_EVENT_PATHS = [
        '/events',
        '/whats-on',
        '/what-s-on',
        '/whats-on/',
        '/events/',
        '/calendar',
        '/listings',
        '/gigs',
        '/shows',
        '/performances',
        '/programme',
        '/program',
        '/schedule',
        '/upcoming',
        '/upcoming-events',
        '/live-events',
        '/activities',
        '/tickets',
        '/events-calendar',
        '/event-listing',
        '/event-listings',
    ];

    public function __construct(public readonly int $websiteId)
    {
        $this->onQueue('crawlers');
    }

    public function handle(): void
    {
        $website = ExternalWebsite::find($this->websiteId);

        if (! $website || $website->crawl_status !== 'pending') {
            return;
        }

        $trustedTld = $this->hasTrustedTld($website->domain);

        // ── Step 1: Domain keyword pre-filter (no HTTP request) ──────────────
        // Skipped for trusted TLDs (.gov.uk, .nhs.uk, etc.) — those organisations
        // are implicitly safe regardless of domain name segments.
        if (! $trustedTld && $this->domainContainsAdultTerm($website->domain)) {
            $website->markBlocked('adult-content');
            Log::info('ProbeExternalWebsiteJob: blocked — adult keyword.', ['domain' => $website->domain]);

            return;
        }

        if (! $trustedTld) {
            // ── Step 3: RTA label check ───────────────────────────────────────
            if ($this->hasRtaLabel($website->domain)) {
                $website->markBlocked('adult-content');
                Log::info('ProbeExternalWebsiteJob: blocked — RTA label.', ['domain' => $website->domain]);

                return;
            }
        }

        // ── Step 5: Event page discovery + markup verification ───────────────
        //
        // Three-tier approach:
        //   1. Plain HTTP on the stored events_page_url (usually the homepage)
        //   2. Plain HTTP on common event paths (/events, /whats-on, etc.)
        //   3. Headless browser (Browsershot) on homepage + common paths as a
        //      last resort for JS-rendered sites (Wix, Squarespace, etc.)
        //
        // When a valid event page is found at a path other than events_page_url,
        // the record is updated so future crawls go directly to the right page.
        $homepage = "https://{$website->domain}";
        $storedUrl = $website->events_page_url ?? $homepage;

        [$foundUrl, $failReason, $usedBrowsershot] = $this->discoverEventPage($website->domain, $storedUrl);

        if ($foundUrl === null) {
            $website->notes = "Probe check failed: {$failReason}";
            $website->save();
            $website->markBlocked('no-events-found');
            Log::info('ProbeExternalWebsiteJob: blocked — no event markup.', [
                'domain' => $website->domain,
                'reason' => $failReason,
            ]);

            return;
        }

        // Persist a better events_page_url if discovery found one
        if ($foundUrl !== $storedUrl) {
            $website->events_page_url = $foundUrl;
        }

        // If plain HTTP never found event markup and Browsershot was required,
        // flag the domain so the crawler skips wasted plain-HTTP attempts.
        if ($usedBrowsershot) {
            $website->needs_browsershot = true;
        }

        $website->crawl_status = 'active';
        $website->save();

        Log::info('ProbeExternalWebsiteJob: activated.', [
            'domain'     => $website->domain,
            'events_url' => $foundUrl,
        ]);

        // ── Step 6: Pre-fetch robots.txt ──────────────────────────────────────
        $this->prefetchRobotsTxt($website);
    }

    // ── Step 1 helpers ────────────────────────────────────────────────────────

    private function domainContainsAdultTerm(string $domain): bool
    {
        // Extract the registrable portion before the TLD (e.g. 'sexshop' from 'sexshop.co.uk')
        $label = preg_replace('/\.(co|org|ac|gov|me|net|sch|nhs|police)\.uk$|\.uk$/i', '', $domain);
        $segments = preg_split('/[-.]/', strtolower($label));

        foreach ($segments as $segment) {
            foreach (self::ADULT_TERMS as $term) {
                // Match exact segment or segment that starts with the term (e.g. 'sexshop' → 'sex')
                if ($segment === $term || str_starts_with($segment, $term)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function hasTrustedTld(string $domain): bool
    {
        foreach (self::TRUSTED_TLDS as $tld) {
            if (str_ends_with($domain, $tld)) {
                return true;
            }
        }

        return false;
    }

    private function hasRtaLabel(string $domain): bool
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'Eventaroo-Bot/1.0 (+https://eventaroo.co.uk/bot)'])
                ->withOptions(['stream' => true])
                ->get("https://{$domain}/");

            if (! $response->successful()) {
                return false;
            }

            // Check response header
            if (stripos($response->header('Rating', ''), 'RTA-5042-1996-1400-1577-RTA') !== false) {
                return true;
            }

            // Meta tags are always in <head> — 64 KB is more than enough.
            $body = $response->getBody()->read(65536);

            if (preg_match('/<meta[^>]+name=["\']rating["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $body, $m)
                || preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']rating["\'][^>]*>/i', $body, $m)) {
                $content = strtolower($m[1]);

                return str_contains($content, 'rta-5042') || $content === 'adult' || $content === 'mature';
            }
        } catch (\Throwable) {
            // Treat as no label on error
        }

        return false;
    }

    // ── Step 5 helpers ────────────────────────────────────────────────────────

    /**
     * Attempt to find a URL on the domain that serves Event JSON-LD.
     *
     * Four-tier approach:
     *   1. Plain HTTP on stored URL / homepage
     *   2. Plain HTTP on common event listing paths (/events, /whats-on, etc.)
     *   2b. Follow one event detail link from any listing page that returned HTML
     *       but no JSON-LD (handles sites like get2events.co.uk where JSON-LD lives
     *       only on individual event pages, not on the listing page itself)
     *   3. Headless browser (Browsershot) for JS-rendered sites, with the same
     *      listing → detail link fallback applied
     *
     * Returns [listingUrl, null, $usedBrowsershot] on success, or
     * [null, failureReason, false] on failure. Always returns the listing page
     * URL (not an individual event detail URL) so the crawler can paginate.
     * The third element is true only when Browsershot was required to find markup,
     * allowing the caller to flag the domain for JS-rendering in future crawls.
     *
     * @return array{string, null, bool}|array{null, string, false}
     */
    private function discoverEventPage(string $domain, string $storedUrl): array
    {
        $homepage   = "https://{$domain}";
        $commonUrls = array_map(
            fn ($path) => rtrim($homepage, '/') . $path,
            self::COMMON_EVENT_PATHS,
        );
        // Cap plain-HTTP attempts: stored URL + the 7 most common event paths.
        // Trying all 21 paths at 5 s each would approach the 120 s job timeout,
        // especially on SPA platforms (Wix, Squarespace) that return 200 for every path.
        $urlsToTry = array_slice(array_unique([$storedUrl, ...$commonUrls]), 0, 8);

        // Collect sitemap URLs once — reused by both Tier 2c (plain HTTP) and
        // Tier 3 (Browsershot) so we don't fetch the sitemap twice.
        $sitemapEventUrls = $this->collectSitemapEventUrls($domain);

        // ── Tiers 1 & 2: plain HTTP on common paths ───────────────────────────
        // Maps listing URL → detail URL for pages that returned HTML but no JSON-LD.
        $detailCandidates = [];

        foreach ($urlsToTry as $url) {
            $html = $this->fetchHtml($url);

            if ($html === null) {
                continue;
            }

            if ($this->htmlHasUkEvents($html)) {
                return [$url, null, false];
            }

            // No JSON-LD on this page — extract one event detail link for tier 2b.
            $detailUrl = $this->findEventDetailLink($html, $url);

            if ($detailUrl !== null) {
                $detailCandidates[$url] = $detailUrl;
            }
        }

        // ── Tier 2b: follow event detail links from listing pages ─────────────
        // Some sites (e.g. Wix-built event sites) only add JSON-LD to individual
        // event pages, not to the /events listing. We fetch one detail page per
        // candidate listing to confirm JSON-LD exists, then return the listing URL
        // so the crawler can paginate through all events from it.
        foreach ($detailCandidates as $listingUrl => $detailUrl) {
            $detailHtml = $this->fetchHtml($detailUrl);

            if ($detailHtml !== null && $this->htmlHasUkEvents($detailHtml)) {
                return [$listingUrl, null, false];
            }
        }

        // ── Tier 2c: sitemap sampling (plain HTTP) ────────────────────────────
        // For sites like genietravel.co.uk whose events live at URLs that aren't
        // in COMMON_EVENT_PATHS, check the sitemap-discovered URLs via plain HTTP.
        // This succeeds for server-rendered sites with non-standard URL structures.
        foreach (array_slice($sitemapEventUrls, 0, 5) as $url) {
            $html = $this->fetchHtml($url);

            if ($html !== null && $this->htmlHasUkEvents($html)) {
                return [$url, null, false];
            }
        }

        // ── Tier 3: headless browser (Browsershot) ────────────────────────────
        // For JS-rendered sites (Wix, Squarespace, etc.) plain HTTP returns
        // unrendered JavaScript. Browsershot is tried on both the common paths
        // AND the sitemap-discovered URLs so we don't miss events at custom paths
        // on JS-rendered sites (the case that breaks genietravel.co.uk).
        if (config('services.browsershot.enabled') && class_exists(\Spatie\Browsershot\Browsershot::class)) {
            $detailCandidates    = [];
            $allBrowsershotUrls  = array_values(array_unique(array_merge($urlsToTry, $sitemapEventUrls)));

            foreach ($allBrowsershotUrls as $url) {
                $html = $this->fetchWithBrowsershot($url);

                if ($html === null) {
                    continue;
                }

                if ($this->htmlHasUkEvents($html)) {
                    return [$url, null, true];
                }

                $detailUrl = $this->findEventDetailLink($html, $url);

                if ($detailUrl !== null) {
                    $detailCandidates[$url] = $detailUrl;
                }
            }

            foreach ($detailCandidates as $listingUrl => $detailUrl) {
                $detailHtml = $this->fetchWithBrowsershot($detailUrl);

                if ($detailHtml !== null && $this->htmlHasUkEvents($detailHtml)) {
                    return [$listingUrl, null, true];
                }
            }

            return [null, 'no Event JSON-LD found after headless browser rendering', false];
        }

        return [null, 'no Event JSON-LD found on homepage, common event paths, or linked event pages', false];
    }

    /**
     * Scan a listing page's HTML for a link that looks like an individual event
     * detail page — i.e. a same-domain href that is a sub-path of the listing URL.
     *
     * Examples:
     *   listing: https://get2events.co.uk/events
     *   detail:  https://get2events.co.uk/events/60s-70s-spring-party-weston-super-mare  ✓
     *
     *   listing: https://example.co.uk/whats-on
     *   detail:  https://example.co.uk/whats-on/summer-gala-2026  ✓
     *
     * Returns an absolute URL or null if no qualifying link is found.
     */
    private function findEventDetailLink(string $html, string $listingUrl): ?string
    {
        preg_match_all('/<a[^>]+href=["\']([^"\'#?][^"\']*)["\'][^>]*>/i', $html, $matches);

        $parsed      = parse_url($listingUrl);
        $origin      = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
        $listingPath = rtrim($parsed['path'] ?? '', '/');

        if ($listingPath === '') {
            // Listing is the homepage — not useful as a prefix filter
            return null;
        }

        foreach ($matches[1] as $href) {
            $href = trim($href);

            // Skip non-page hrefs
            if (str_starts_with($href, 'javascript:') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                continue;
            }

            // Resolve to absolute URL
            if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
                $absolute = $href;
            } elseif (str_starts_with($href, '/')) {
                $absolute = $origin . $href;
            } else {
                continue; // Relative paths without leading slash are too ambiguous
            }

            $hrefParsed = parse_url($absolute);

            // Must be on the same host
            if (($hrefParsed['host'] ?? '') !== ($parsed['host'] ?? '')) {
                continue;
            }

            $hrefPath = rtrim($hrefParsed['path'] ?? '', '/');

            // Must be a deeper path under the listing page (e.g. /events/my-event)
            if (str_starts_with($hrefPath, $listingPath . '/') && strlen($hrefPath) > strlen($listingPath) + 1) {
                return $absolute;
            }
        }

        return null;
    }

    /**
     * Collect up to five event-like URLs from the domain's sitemap(s).
     *
     * Handles both regular sitemaps and sitemap indexes (one level of children).
     * Returns an empty array if no sitemap is reachable or it contains no URLs.
     * Does NOT fetch the candidate URLs — that is left to the caller so the same
     * list can be used for both the plain-HTTP and Browsershot passes.
     *
     * @return list<string>
     */
    private function collectSitemapEventUrls(string $domain): array
    {
        $sitemapPaths = ['/sitemap.xml', '/sitemap_index.xml'];

        foreach ($sitemapPaths as $path) {
            $xml = $this->fetchSitemapXml("https://{$domain}{$path}");

            if ($xml === null) {
                continue;
            }

            // Sitemap index — walk child sitemaps one level deep.
            if (isset($xml->sitemap)) {
                foreach ($xml->sitemap as $child) {
                    $childUrl = (string) $child->loc;

                    if (! $childUrl) {
                        continue;
                    }

                    $childXml = $this->fetchSitemapXml($childUrl);

                    if ($childXml === null) {
                        continue;
                    }

                    $urls = $this->rankUrlsFromSitemapXml($childXml, $domain);

                    if (! empty($urls)) {
                        return array_slice($urls, 0, 5);
                    }
                }

                continue;
            }

            // Regular sitemap.
            $urls = $this->rankUrlsFromSitemapXml($xml, $domain);

            if (! empty($urls)) {
                return array_slice($urls, 0, 5);
            }
        }

        return [];
    }

    /**
     * Extract URLs from a sitemap XML object, ranking event-like paths first.
     *
     * @return list<string>
     */
    private function rankUrlsFromSitemapXml(\SimpleXMLElement $xml, string $domain): array
    {
        $urls = [];

        foreach ($xml->url as $entry) {
            $loc = (string) $entry->loc;

            if ($loc && str_contains($loc, $domain)) {
                $urls[] = $loc;
            }
        }

        if (empty($urls)) {
            return [];
        }

        $eventSegments = ['/event', '/show', '/gig', '/ticket', '/performance', '/programme', '/whats-on', '/listing'];

        $ranked = array_values(array_filter(
            $urls,
            function (string $url) use ($eventSegments): bool {
                $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');

                foreach ($eventSegments as $segment) {
                    if (str_contains($path, $segment)) {
                        return true;
                    }
                }

                return false;
            }
        ));

        // Fall back to first few generic URLs if none match event-like paths.
        return ! empty($ranked) ? $ranked : array_slice($urls, 0, 5);
    }

    /**
     * Fetch a sitemap URL and parse it as SimpleXML. Returns null on failure,
     * non-XML responses, or XML parse errors.
     */
    private function fetchSitemapXml(string $url): ?\SimpleXMLElement
    {
        $body = $this->fetchHtml($url);

        if ($body === null) {
            return null;
        }

        // Reject obviously non-XML responses (e.g. HTML 404 pages).
        $trimmed = ltrim($body);

        if (! str_starts_with($trimmed, '<?xml') && ! str_starts_with($trimmed, '<urlset') && ! str_starts_with($trimmed, '<sitemapindex')) {
            return null;
        }

        $xml = @simplexml_load_string($body);

        return $xml === false ? null : $xml;
    }

    /**
     * Fetch a page via plain HTTP and return the body, or null on failure.
     */
    private function fetchHtml(string $url): ?string
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'Eventaroo-Bot/1.0 (+https://eventaroo.co.uk/bot)'])
                ->withOptions(['stream' => true])
                ->get($url);

            if ($response->status() !== 200) {
                return null;
            }

            return $response->getBody()->read(1048576); // 1 MB cap
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Fetch a page via headless Chromium (Browsershot / Puppeteer) and return
     * the fully-rendered HTML, or null on failure.
     *
     * Only called when BROWSERSHOT_ENABLED=true and spatie/browsershot is installed.
     */
    private function fetchWithBrowsershot(string $url): ?string
    {
        try {
            /** @var \Spatie\Browsershot\Browsershot $shot */
            $shot = \Spatie\Browsershot\Browsershot::url($url)
                ->setNodeBinary(config('services.browsershot.node_binary', 'node'))
                ->setNodeModulesPath(base_path('node_modules'))
                ->userAgent('Eventaroo-Bot/1.0 (+https://eventaroo.co.uk/bot)')
                ->timeout(15000)
                ->waitUntilNetworkIdle();

            return $shot->bodyHtml();
        } catch (\Throwable $e) {
            Log::warning('ProbeExternalWebsiteJob: Browsershot failed.', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Return true if the HTML contains at least one Event JSON-LD block
     * with a UK postcode in the location.
     */
    private function htmlHasUkEvents(string $html): bool
    {
        $events = $this->extractJsonLdEvents($html);

        if (empty($events)) {
            return false;
        }

        return collect($events)->contains(function (array $event): bool {
            $postcode = data_get($event, 'location.address.postalCode')
                ?? data_get($event, 'location.postalCode')
                ?? '';

            return (bool) preg_match('/[A-Z]{1,2}[0-9][0-9A-Z]?\s?[0-9][A-Z]{2}/i', (string) $postcode);
        });
    }

    /**
     * Extract all @type:Event objects from JSON-LD script tags in HTML.
     *
     * @return list<array<string, mixed>>
     */
    private function extractJsonLdEvents(string $html): array
    {
        $events = [];

        preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches);

        foreach ($matches[1] as $json) {
            $data = json_decode(trim($json), true);

            if (! is_array($data)) {
                continue;
            }

            foreach ($this->collectEventObjects($data) as $event) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectEventObjects(array $data): array
    {
        $events = [];

        // Unwrap @graph
        if (isset($data['@graph']) && is_array($data['@graph'])) {
            foreach ($data['@graph'] as $item) {
                if (is_array($item)) {
                    $events = array_merge($events, $this->collectEventObjects($item));
                }
            }

            return $events;
        }

        // Array of objects
        if (isset($data[0])) {
            foreach ($data as $item) {
                if (is_array($item)) {
                    $events = array_merge($events, $this->collectEventObjects($item));
                }
            }

            return $events;
        }

        // Single object — check @type
        $type = $data['@type'] ?? null;
        $types = is_array($type) ? $type : [$type];

        if (in_array('Event', $types, true)) {
            $events[] = $data;
        }

        return $events;
    }

    // ── Step 6 helpers ────────────────────────────────────────────────────────

    private function prefetchRobotsTxt(ExternalWebsite $website): void
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'Eventaroo-Bot/1.0 (+https://eventaroo.co.uk/bot)'])
                ->get("https://{$website->domain}/robots.txt");

            if ($response->successful()) {
                $website->robots_txt            = $response->body();
                $website->robots_txt_fetched_at = now();
                $website->save();
            }
        } catch (\Throwable) {
            // Best-effort — failure here does not affect active status
        }
    }
}
