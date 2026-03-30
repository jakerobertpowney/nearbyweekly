<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add rate limit tracking columns to ingestion_logs.
     *
     * These columns record the API quota state at the end of each ingestion run,
     * making it possible to monitor burn rate across providers without needing to
     * instrument every individual API call.
     *
     * rate_limit_remaining — quota units remaining after the run (calls or tokens, provider-specific)
     * rate_limit_total     — total quota for the window (when available)
     * rate_limit_reset_at  — when the quota window resets (when available)
     */
    public function up(): void
    {
        Schema::table('ingestion_logs', function (Blueprint $table) {
            $table->integer('rate_limit_remaining')->nullable()->after('failed');
            $table->integer('rate_limit_total')->nullable()->after('rate_limit_remaining');
            $table->dateTime('rate_limit_reset_at')->nullable()->after('rate_limit_total');
        });
    }

    public function down(): void
    {
        Schema::table('ingestion_logs', function (Blueprint $table) {
            $table->dropColumn(['rate_limit_remaining', 'rate_limit_total', 'rate_limit_reset_at']);
        });
    }
};
