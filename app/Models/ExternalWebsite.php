<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExternalWebsite extends Model
{
    protected $fillable = [
        'domain',
        'events_page_url',
        'sitemap_url',
        'robots_txt',
        'robots_txt_fetched_at',
        'crawl_status',
        'blocked_reason',
        'needs_browsershot',
        'consecutive_failures',
        'last_scanned_at',
        'next_scan_at',
        'events_found_last_scan',
        'total_events_ingested',
        'discovery_source',
        'discovery_crawl_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'robots_txt_fetched_at'  => 'datetime',
            'last_scanned_at'        => 'datetime',
            'next_scan_at'           => 'datetime',
            'consecutive_failures'   => 'integer',
            'events_found_last_scan' => 'integer',
            'total_events_ingested'  => 'integer',
            'needs_browsershot'      => 'boolean',
        ];
    }

    // ── Local scopes ─────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('crawl_status', 'active');
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('next_scan_at')
              ->orWhere('next_scan_at', '<=', now());
        });
    }

    // ── State transitions ─────────────────────────────────────────────────────

    /**
     * Record a crawl failure.
     *
     * Increments `consecutive_failures`. Sets `crawl_status = 'error'` once
     * five consecutive failures have accumulated so the domain is excluded from
     * the next dispatch query.
     */
    public function markFailure(): void
    {
        $this->increment('consecutive_failures');
        $this->refresh();

        if ($this->consecutive_failures >= 5) {
            $this->crawl_status = 'error';
            $this->save();
        }
    }

    /**
     * Record a successful crawl run.
     *
     * Resets the failure counter, records timing statistics, and schedules the
     * next crawl for one week from now.
     */
    public function markSuccess(int $eventsFound): void
    {
        $this->consecutive_failures   = 0;
        $this->last_scanned_at        = now();
        $this->events_found_last_scan = $eventsFound;
        $this->total_events_ingested  += $eventsFound;
        $this->next_scan_at           = now()->addWeek();
        $this->save();
    }

    /**
     * Permanently block the domain.
     *
     * Blocked domains are excluded from all dispatch queries and cannot be
     * re-queued without a manual status reset.
     */
    public function markBlocked(string $reason): void
    {
        $this->crawl_status   = 'blocked';
        $this->blocked_reason = $reason;
        $this->save();
    }

    /**
     * Flag that this site requires a headless browser to render Event JSON-LD.
     *
     * Set by ProbeExternalWebsiteJob when plain-HTTP fetches found no markup but
     * Browsershot succeeded. CrawlExternalWebsiteJob reads this flag to skip the
     * wasted plain-HTTP attempt and go straight to Browsershot on every page.
     */
    public function markNeedsBrowsershot(): void
    {
        $this->needs_browsershot = true;
        $this->save();
    }
}
