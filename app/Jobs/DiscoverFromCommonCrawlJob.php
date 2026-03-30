<?php

namespace App\Jobs;

use App\Models\ExternalWebsite;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscoverFromCommonCrawlJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 7200; // 2 hours — WDC files are large

    public int $tries = 2;

    /** UK TLDs we care about */
    private const UK_TLDS = [
        '.co.uk', '.org.uk', '.ac.uk', '.gov.uk', '.me.uk',
        '.net.uk', '.sch.uk', '.nhs.uk', '.police.uk', '.uk',
    ];

    /** CC Index supplementary TLDs — no wildcard prefix; matchType=domain matches all subdomains */
    private const CC_TLDS = ['co.uk', 'org.uk', 'ac.uk', 'gov.uk'];

    public function __construct(public readonly bool $dryRun = false)
    {
        $this->onQueue('common-crawl');
    }

    public function handle(): void
    {
        $inserted = 0;
        $existed = 0;
        $probes = 0;

        // ── Step 1: Resolve latest WDC Event dataset URL ──────────────────────
        // NOTE: WDC changed their format in 2023. Snapshots no longer include a
        // per-type Event.jsonld.gz file — data is now split across 900 N-Quads
        // part files. The WDC phase is skipped until the importer is updated to
        // handle the new format. CC Index (Step 4) runs regardless.
        $wdcUrl = $this->resolveWdcUrl();

        if ($wdcUrl !== null) {
            Log::info('DiscoverFromCommonCrawlJob: using WDC dataset.', ['url' => $wdcUrl]);

            // ── Step 2: Download to temp file (streaming) ─────────────────────
            $tempDir = storage_path('app/temp');
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            $tempPath = $tempDir.'/wdc-events-'.now()->format('YmdHis').'.ndjson.gz';

            try {
                $response = Http::timeout(300)->sink($tempPath)->get($wdcUrl);

                if ($response->status() !== 200) {
                    Log::warning('DiscoverFromCommonCrawlJob: WDC download failed — skipping WDC phase.', ['status' => $response->status(), 'url' => $wdcUrl]);
                } elseif (str_contains($response->header('Content-Type') ?? '', 'text/html')) {
                    Log::warning('DiscoverFromCommonCrawlJob: WDC URL returned HTML instead of gzip — file likely does not exist in this snapshot. Skipping WDC phase.', ['url' => $wdcUrl]);
                } else {
                    // ── Step 3: Stream-process the gzipped NDJSON ────────────────
                    [$inserted, $existed] = $this->processWdcFile($tempPath);
                }
            } finally {
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
            }
        } else {
            Log::warning('DiscoverFromCommonCrawlJob: could not resolve WDC Event dataset URL — skipping WDC phase.');
        }

        // ── Step 4: CC Index supplementary queries ───────────────────────────
        [$ccInserted, $ccExisted] = $this->processCcIndex();
        $inserted += $ccInserted;
        $existed += $ccExisted;

        // ── Step 6: Probe newly inserted domains ──────────────────────────────
        if (! $this->dryRun) {
            $newDomains = ExternalWebsite::query()
                ->where('crawl_status', 'pending')
                ->whereNull('last_scanned_at')
                ->whereNull('robots_txt_fetched_at')
                ->get();

            foreach ($newDomains as $website) {
                ProbeExternalWebsiteJob::dispatch($website->id);
                $probes++;
            }
        }

        // ── Step 7: Summary log ───────────────────────────────────────────────
        Log::info('DiscoverFromCommonCrawlJob: complete.', [
            'new_domains' => $inserted,
            'existing' => $existed,
            'probes' => $probes,
            'total' => ExternalWebsite::query()->count(),
            'dry_run' => $this->dryRun,
        ]);
    }

    // ── WDC ───────────────────────────────────────────────────────────────────

    private function resolveWdcUrl(): ?string
    {
        $response = Http::timeout(15)->get('http://webdatacommons.org/structureddata/');

        if (! $response->successful()) {
            return null;
        }

        // Find all snapshot links like /structureddata/2023-12/
        preg_match_all('#/structureddata/(\d{4}-\d{2})/#', $response->body(), $matches);

        if (empty($matches[1])) {
            return null;
        }

        $latest = collect($matches[1])->sort()->last();

        return "http://webdatacommons.org/structureddata/{$latest}/data/Event.jsonld.gz";
    }

    /**
     * @return array{int, int} [inserted, existed]
     */
    private function processWdcFile(string $path): array
    {
        $handle = gzopen($path, 'r');

        if ($handle === false) {
            Log::error('DiscoverFromCommonCrawlJob: could not open WDC file.');

            return [0, 0];
        }

        $inserted = 0;
        $existed = 0;

        try {
            while (! gzeof($handle)) {
                $line = gzgets($handle, 65536);

                if ($line === false) {
                    break;
                }

                $data = json_decode(trim($line), true);

                if (! is_array($data)) {
                    continue;
                }

                $url = $data['url'] ?? $data['@id'] ?? null;

                if (! $url) {
                    continue;
                }

                $domain = $this->extractDomain($url);

                if ($domain === null || ! $this->isUkDomain($domain)) {
                    continue;
                }

                $result = $this->upsertDomain($domain, $url, 'wdc', null);
                $result === 'inserted' ? $inserted++ : $existed++;
            }
        } finally {
            gzclose($handle);
        }

        return [$inserted, $existed];
    }

    // ── CC Index ──────────────────────────────────────────────────────────────

    /**
     * @return array{int, int} [inserted, existed]
     */
    private function processCcIndex(): array
    {
        $crawlId = $this->resolveLatestCcCrawlId();
        $inserted = 0;
        $existed = 0;

        foreach (self::CC_TLDS as $tld) {
            $url = "https://index.commoncrawl.org/{$crawlId}-index"
                ."?url={$tld}&output=json&matchType=domain&filter=mime:text/html&limit=10000";

            $response = Http::timeout(60)->get($url);

            if (! $response->successful()) {
                Log::warning('DiscoverFromCommonCrawlJob: CC Index query failed.', ['tld' => $tld, 'status' => $response->status(), 'url' => $url]);
                continue;
            }

            Log::info('DiscoverFromCommonCrawlJob: CC Index query succeeded.', ['tld' => $tld, 'bytes' => strlen($response->body())]);

            foreach (explode("\n", $response->body()) as $line) {
                $record = json_decode(trim($line), true);

                if (! is_array($record)) {
                    continue;
                }

                $pageUrl = $record['url'] ?? null;

                if (! $pageUrl) {
                    continue;
                }

                $domain = $this->extractDomain($pageUrl);

                if ($domain === null || ! $this->isUkDomain($domain)) {
                    continue;
                }

                // Skip domains we already know about
                if (ExternalWebsite::query()->where('domain', $domain)->exists()) {
                    $existed++;

                    continue;
                }

                // Sample the page live to verify Event JSON-LD before inserting
                if (! $this->pageHasEventJsonLd($pageUrl)) {
                    continue;
                }

                $result = $this->upsertDomain($domain, $pageUrl, 'cc-index', $crawlId);
                $result === 'inserted' ? $inserted++ : $existed++;
            }
        }

        return [$inserted, $existed];
    }

    private function resolveLatestCcCrawlId(): string
    {
        // Fallback to a known recent crawl ID if the API is unavailable
        $response = Http::timeout(10)->get('https://index.commoncrawl.org/collinfo.json');

        if ($response->successful()) {
            $crawls = $response->json();

            if (is_array($crawls) && ! empty($crawls)) {
                logger($crawls[0]['id'] ?? 'CC-MAIN-2026-13');

                return $crawls[0]['id'] ?? 'CC-MAIN-2026-12';
            }
        }

        return 'CC-MAIN-2026-12';
    }

    private function pageHasEventJsonLd(string $url): bool
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'Eventaroo-Bot/1.0 (+https://eventaroo.co.uk/bot)'])
                ->get($url);

            if (! $response->successful()) {
                return false;
            }

            return str_contains($response->body(), '"@type":"Event"')
                || str_contains($response->body(), '"@type": "Event"');
        } catch (\Throwable) {
            return false;
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function extractDomain(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            return null;
        }

        return strtolower(preg_replace('/^www\./', '', $host));
    }

    private function isUkDomain(string $domain): bool
    {
        foreach (self::UK_TLDS as $tld) {
            if (str_ends_with($domain, $tld)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Upsert a domain into external_websites.
     *
     * Existing rows are never overwritten — this job is additive only.
     *
     * @return 'inserted'|'existed'
     */
    private function upsertDomain(string $domain, string $url, string $source, ?string $crawlId): string
    {
        if (ExternalWebsite::query()->where('domain', $domain)->exists()) {
            return 'existed';
        }

        if (! $this->dryRun) {
            ExternalWebsite::query()->create([
                'domain' => $domain,
                'events_page_url' => $url,
                'crawl_status' => 'pending',
                'discovery_source' => $source,
                'discovery_crawl_id' => $crawlId,
            ]);
        }

        return 'inserted';
    }
}
