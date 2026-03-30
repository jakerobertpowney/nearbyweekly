<?php

namespace App\Jobs;

use App\Models\ExternalWebsite;
use App\Models\IngestionLog;
use App\Services\Events\EventIngestionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CrawlExternalWebsiteJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600; // 10 min per domain

    public int $tries = 2;

    public int $backoff = 300;

    private const USER_AGENT = 'Eventaroo-Bot/1.0 (+https://eventaroo.co.uk/bot)';

    private const MAX_URLS = 300;

    private const MAX_RESPONSE_BYTES = 1_048_576; // 1 MB

    private const DEFAULT_CRAWL_DELAY_SECONDS = 2;

    /**
     * Maximum Browsershot calls per job run.
     *
     * Each call can take up to 15 s (the Puppeteer timeout). Capping at 30 keeps
     * the worst-case Browsershot time well within the 600 s job timeout, even for
     * large JS-rendered sites with hundreds of event URLs in their sitemap.
     * URLs beyond the cap are skipped rather than timing out the job.
     */
    private const BROWSERSHOT_URL_CAP = 30;

    /** Shared ExternalWebsite instance for the duration of this job run. */
    private ExternalWebsite $website;

    /** Running count of Browsershot calls made in this job run. */
    private int $browsershotCallCount = 0;

    /** URL path segments that suggest an events listing page */
    private const EVENT_PATH_SEGMENTS = [
        '/events', '/whats-on', '/what-s-on', '/listings',
        '/programme', '/performances', '/shows', '/gigs', '/tickets',
    ];

    /** Fallback sitemap paths to probe when not specified in robots.txt */
    private const SITEMAP_FALLBACKS = [
        '/sitemap.xml',
        '/sitemap_index.xml',
        '/sitemap-events.xml',
        '/whats-on/sitemap.xml',
    ];

    public function __construct(public readonly int $websiteId)
    {
        $this->onQueue('crawlers');
    }

    public function handle(EventIngestionService $ingestionService): void
    {
        $website = ExternalWebsite::find($this->websiteId);

        if (! $website || $website->crawl_status !== 'active') {
            return;
        }

        $this->website = $website;

        // ── Step 1: Fetch / refresh robots.txt ───────────────────────────────
        if (! $this->ensureRobotsTxt($website)) {
            $website->markFailure();

            return;
        }

        $rules = $this->parseRobotsTxt($website->robots_txt ?? '');

        // ── Step 2: Check crawl permission ────────────────────────────────────
        if ($this->isFullyDisallowed($rules)) {
            $website->blocked_reason = 'robots-disallowed';
            $website->crawl_status   = 'paused';
            $website->save();

            Log::info('CrawlExternalWebsiteJob: paused — robots.txt disallows.', [
                'domain' => $website->domain,
            ]);

            return;
        }

        // ── Step 3: Discover sitemap ──────────────────────────────────────────
        $sitemapUrl = $this->resolveSitemapUrl($website, $rules);

        if ($sitemapUrl === null && ! filled($website->events_page_url)) {
            $website->markFailure();

            return;
        }

        if ($sitemapUrl && $sitemapUrl !== $website->sitemap_url) {
            $website->sitemap_url = $sitemapUrl;
            $website->save();
        }

        // ── Step 4: Collect event URLs ────────────────────────────────────────
        $urls = $sitemapUrl
            ? $this->urlsFromSitemap($sitemapUrl, $website->domain)
            : [$website->events_page_url];

        if (empty($urls) && filled($website->events_page_url)) {
            $urls = [$website->events_page_url];
        }

        $crawlDelay = $rules['crawl_delay'] ?? self::DEFAULT_CRAWL_DELAY_SECONDS;

        // ── Step 5 & 6: Extract and normalise events ──────────────────────────
        $normalisedEvents = [];
        $now              = Carbon::now();

        foreach ($urls as $url) {
            if ($this->isDisallowed($url, $rules)) {
                continue;
            }

            $html = $this->fetchPage($url);

            if ($html === null) {
                sleep($crawlDelay);

                continue;
            }

            // Respect noindex
            if ($this->hasNoIndex($html)) {
                sleep($crawlDelay);

                continue;
            }

            foreach ($this->extractEvents($html, $url) as $event) {
                $normalised = $this->normalise($event, $url, $website->domain);

                if ($normalised === null) {
                    continue;
                }

                // Skip past events
                if (Carbon::parse($normalised['starts_at'])->lt($now)) {
                    continue;
                }

                $normalisedEvents[] = $normalised;
            }

            sleep($crawlDelay);
        }

        // ── Step 7: Persist ───────────────────────────────────────────────────
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($normalisedEvents as $payload) {
            try {
                $result = $ingestionService->persist($payload);
                $stats[$result]++;
            } catch (\Throwable $e) {
                $stats['failed']++;
                Log::warning('CrawlExternalWebsiteJob: persist failed.', [
                    'domain' => $website->domain,
                    'title'  => $payload['title'] ?? null,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        // ── Step 8: Update website record ─────────────────────────────────────
        $website->markSuccess(count($normalisedEvents));

        // ── Step 9: Write ingestion log ───────────────────────────────────────
        IngestionLog::query()->create([
            'provider' => 'schema-org:'.$website->domain,
            'status'   => 'success',
            'fetched'  => count($normalisedEvents),
            'created'  => $stats['created'],
            'updated'  => $stats['updated'],
            'skipped'  => $stats['skipped'],
            'failed'   => $stats['failed'],
            'ran_at'   => now(),
        ]);

        Log::info('CrawlExternalWebsiteJob: complete.', [
            'domain'          => $website->domain,
            'urls_crawled'    => count($urls),
            'events_found'    => count($normalisedEvents),
            'created'         => $stats['created'],
        ]);
    }

    // ── robots.txt ────────────────────────────────────────────────────────────

    private function ensureRobotsTxt(ExternalWebsite $website): bool
    {
        $stale = $website->robots_txt_fetched_at === null
            || $website->robots_txt_fetched_at->lt(now()->subHours(24));

        if (! $stale) {
            return true;
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get("https://{$website->domain}/robots.txt");

            if (! $response->successful()) {
                Log::warning('CrawlExternalWebsiteJob: robots.txt unreachable.', [
                    'domain' => $website->domain,
                    'status' => $response->status(),
                ]);

                return false;
            }

            $website->robots_txt            = $response->body();
            $website->robots_txt_fetched_at = now();
            $website->save();
        } catch (\Throwable $e) {
            Log::warning('CrawlExternalWebsiteJob: robots.txt fetch failed.', [
                'domain' => $website->domain,
                'error'  => $e->getMessage(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * @return array{disallowed: list<string>, crawl_delay: int|null, sitemaps: list<string>}
     */
    private function parseRobotsTxt(string $content): array
    {
        $rules      = ['disallowed' => [], 'crawl_delay' => null, 'sitemaps' => []];
        $applicable = false;

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/^User-agent:\s*(.+)$/i', $line, $m)) {
                $agent      = strtolower(trim($m[1]));
                $applicable = $agent === '*' || $agent === 'eventaroo-bot';
            } elseif ($applicable && preg_match('/^Disallow:\s*(.+)$/i', $line, $m)) {
                $rules['disallowed'][] = trim($m[1]);
            } elseif ($applicable && preg_match('/^Crawl-delay:\s*(\d+)/i', $line, $m)) {
                $rules['crawl_delay'] = (int) $m[1];
            } elseif (preg_match('/^Sitemap:\s*(.+)$/i', $line, $m)) {
                $rules['sitemaps'][] = trim($m[1]);
            }
        }

        return $rules;
    }

    private function isFullyDisallowed(array $rules): bool
    {
        return in_array('/', $rules['disallowed'], true);
    }

    private function isDisallowed(string $url, array $rules): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '/';

        foreach ($rules['disallowed'] as $disallowed) {
            if ($disallowed !== '' && str_starts_with($path, $disallowed)) {
                return true;
            }
        }

        return false;
    }

    // ── Sitemap ───────────────────────────────────────────────────────────────

    private function resolveSitemapUrl(ExternalWebsite $website, array $rules): ?string
    {
        // 1. From robots.txt Sitemap: directive
        if (! empty($rules['sitemaps'])) {
            return $rules['sitemaps'][0];
        }

        // 2. Try well-known fallback paths
        foreach (self::SITEMAP_FALLBACKS as $path) {
            $url = "https://{$website->domain}{$path}";

            try {
                $response = Http::timeout(8)
                    ->withHeaders(['User-Agent' => self::USER_AGENT])
                    ->head($url);

                if ($response->successful()) {
                    return $url;
                }
            } catch (\Throwable) {
                // Try next
            }
        }

        return null;
    }

    /**
     * Parse a sitemap (or sitemap index) and return event-like URLs, capped at MAX_URLS.
     *
     * @return list<string>
     */
    private function urlsFromSitemap(string $sitemapUrl, string $domain): array
    {
        $urls = $this->parseSitemap($sitemapUrl, $domain, followIndex: true);

        return array_slice(
            array_filter($urls, fn (string $u): bool => $this->isEventUrl($u)),
            0,
            self::MAX_URLS,
        );
    }

    /**
     * @return list<string>
     */
    private function parseSitemap(string $url, string $domain, bool $followIndex): array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get($url);

            if (! $response->successful()) {
                return [];
            }

            $xml = @simplexml_load_string($response->body());

            if ($xml === false) {
                return [];
            }

            // Sitemap index — follow one level of children
            if ($followIndex && isset($xml->sitemap)) {
                $urls = [];

                foreach ($xml->sitemap as $child) {
                    $childUrl = (string) $child->loc;

                    if ($childUrl) {
                        $urls = array_merge($urls, $this->parseSitemap($childUrl, $domain, followIndex: false));
                    }
                }

                return $urls;
            }

            // Regular sitemap
            $urls = [];

            foreach ($xml->url as $entry) {
                $loc = (string) $entry->loc;

                if ($loc && str_contains($loc, $domain)) {
                    $urls[] = $loc;
                }
            }

            return $urls;
        } catch (\Throwable) {
            return [];
        }
    }

    private function isEventUrl(string $url): bool
    {
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');

        foreach (self::EVENT_PATH_SEGMENTS as $segment) {
            if (str_contains($path, $segment)) {
                return true;
            }
        }

        return false;
    }

    // ── Page fetching ─────────────────────────────────────────────────────────

    /**
     * Fetch a page's HTML, with a cap-aware Browsershot fallback.
     *
     * Two strategies depending on whether the domain is flagged as JS-rendered:
     *
     * Known JS-rendered site (`needs_browsershot = true`):
     *   Plain HTTP is skipped entirely — it will never serve JSON-LD for these
     *   sites. Browsershot is called directly, subject to BROWSERSHOT_URL_CAP.
     *   Once the cap is reached, the URL returns null and is skipped for this run.
     *
     * Standard site (`needs_browsershot = false`):
     *   Plain HTTP is tried first. If it returns HTML with Event JSON-LD, that is
     *   returned immediately (fast path). If plain HTTP succeeds but has no JSON-LD,
     *   Browsershot is tried as a fallback (handles partially-JS sites). If
     *   Browsershot is unavailable or the cap is hit, the plain HTML is returned
     *   as-is (extractEvents will find nothing, which is the correct outcome).
     */
    private function fetchPage(string $url): ?string
    {
        $needsBrowsershot    = $this->website->needs_browsershot;
        $browsershotReady    = config('services.browsershot.enabled')
            && class_exists(\Spatie\Browsershot\Browsershot::class);

        if (! $needsBrowsershot) {
            $html = $this->fetchPlain($url);

            // Fast path: plain HTTP returned usable JSON-LD markup.
            if ($html !== null && $this->hasEventJsonLd($html)) {
                return $html;
            }

            // Plain HTML present but no JSON-LD. If Browsershot isn't available,
            // return the plain HTML — it's all we have.
            if (! $browsershotReady) {
                return $html;
            }

            // Browsershot may reveal JS-injected markup — fall through.
        }

        // ── Browsershot path ──────────────────────────────────────────────────

        if (! $browsershotReady) {
            // JS-rendered site but Browsershot is not configured — nothing we can do.
            return null;
        }

        if ($this->browsershotCallCount >= self::BROWSERSHOT_URL_CAP) {
            // Hard cap reached. Log once per domain (on the first URL that hits it).
            if ($this->browsershotCallCount === self::BROWSERSHOT_URL_CAP) {
                Log::warning('CrawlExternalWebsiteJob: Browsershot cap reached — remaining JS pages skipped.', [
                    'domain' => $this->website->domain,
                    'cap'    => self::BROWSERSHOT_URL_CAP,
                ]);

                // Increment past the cap so the warning logs only once.
                $this->browsershotCallCount++;
            }

            return null;
        }

        $this->browsershotCallCount++;

        return $this->fetchWithBrowsershot($url);
    }

    /**
     * Plain HTTP fetch — fast path used on every URL.
     */
    private function fetchPlain(string $url): ?string
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
            ])->timeout(10)->withOptions(['stream' => true])->get($url);

            if (! $response->successful()) {
                return null;
            }

            // Read up to the size cap — never buffer the full response first.
            $body = $response->getBody()->read(self::MAX_RESPONSE_BYTES);

            return $body !== '' ? $body : null;
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
                ->userAgent(self::USER_AGENT)
                ->timeout(15000)
                ->waitUntilNetworkIdle();

            return $shot->bodyHtml();
        } catch (\Throwable $e) {
            Log::warning('CrawlExternalWebsiteJob: Browsershot failed.', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Quick check: does this HTML contain at least one JSON-LD script block
     * with @type Event? Used to decide whether Browsershot is needed.
     */
    private function hasEventJsonLd(string $html): bool
    {
        preg_match_all(
            '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si',
            $html,
            $matches,
        );

        foreach ($matches[1] as $json) {
            $data = json_decode(trim($json), true);

            if (! is_array($data)) {
                continue;
            }

            if (! empty($this->collectEventObjects($data))) {
                return true;
            }
        }

        return false;
    }

    private function hasNoIndex(string $html): bool
    {
        return (bool) preg_match(
            '/<meta[^>]+name=["\']robots["\'][^>]+content=["\'][^"\']*noindex/i',
            $html
        );
    }

    // ── JSON-LD extraction ────────────────────────────────────────────────────

    /**
     * @return list<array<string, mixed>>
     */
    private function extractEvents(string $html, string $pageUrl): array
    {
        $events = [];

        preg_match_all(
            '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si',
            $html,
            $matches,
        );

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
        if (array_is_list($data) && isset($data[0])) {
            foreach ($data as $item) {
                if (is_array($item)) {
                    $events = array_merge($events, $this->collectEventObjects($item));
                }
            }

            return $events;
        }

        // Single object — check @type
        $type  = $data['@type'] ?? null;
        $types = is_array($type) ? $type : [$type];

        if (in_array('Event', $types, true)) {
            $events[] = $data;
        }

        return $events;
    }

    // ── Normalisation ─────────────────────────────────────────────────────────

    /**
     * Map a schema.org Event object to Eventaroo's internal payload format.
     *
     * Returns null if mandatory fields (starts_at, postcode) are absent.
     *
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>|null
     */
    private function normalise(array $event, string $pageUrl, string $domain): ?array
    {
        $startsAt = $event['startDate'] ?? null;

        if (! filled($startsAt)) {
            return null;
        }

        $postcode = data_get($event, 'location.address.postalCode')
            ?? data_get($event, 'location.postalCode')
            ?? null;

        if (! filled($postcode)) {
            return null;
        }

        // Validate UK postcode format
        if (! preg_match('/[A-Z]{1,2}[0-9][0-9A-Z]?\s?[0-9][A-Z]{2}/i', (string) $postcode)) {
            return null;
        }

        $url = $event['url'] ?? $pageUrl;

        $imageUrl = null;
        $image    = $event['image'] ?? null;

        if (is_string($image)) {
            $imageUrl = $image;
        } elseif (is_array($image)) {
            $imageUrl = $image['url'] ?? ($image[0]['url'] ?? null);
        }

        $title = (string) ($event['name'] ?? '');

        if (! filled($title)) {
            return null;
        }

        return [
            'source'       => 'schema-org',
            'external_id'  => md5($url),
            'title'        => $title,
            'slug'         => Str::slug($title),
            'description'  => strip_tags((string) ($event['description'] ?? '')),
            'category'     => null,
            'venue_name'   => data_get($event, 'location.name'),
            'address_line' => data_get($event, 'location.address.streetAddress'),
            'city'         => data_get($event, 'location.address.addressLocality'),
            'postcode'     => strtoupper(trim((string) $postcode)),
            'latitude'     => null,
            'longitude'    => null,
            'starts_at'    => $startsAt,
            'ends_at'      => $event['endDate'] ?? null,
            'url'          => $url,
            'image_url'    => $imageUrl,
            'score_manual' => 0,
            'raw_payload'  => $event,
        ];
    }
}
