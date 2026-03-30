<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_websites', function (Blueprint $table) {
            $table->id();

            $table->string('domain')->unique();
            $table->string('events_page_url')->nullable();
            $table->string('sitemap_url')->nullable();
            $table->text('robots_txt')->nullable();
            $table->timestamp('robots_txt_fetched_at')->nullable();

            // pending | active | paused | error | blocked
            $table->string('crawl_status')->default('pending');
            // adult-content | malicious | robots-disallowed | no-events-found | probe-failed
            $table->string('blocked_reason')->nullable();

            $table->integer('consecutive_failures')->default(0); // auto-set to error after 5
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamp('next_scan_at')->nullable(); // null = crawl immediately when next due

            $table->integer('events_found_last_scan')->default(0);
            $table->integer('total_events_ingested')->default(0);

            $table->string('discovery_source');           // wdc | cc-index | manual
            $table->string('discovery_crawl_id')->nullable(); // e.g. CC-MAIN-2026-13
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('crawl_status');
            $table->index(['crawl_status', 'next_scan_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_websites');
    }
};
