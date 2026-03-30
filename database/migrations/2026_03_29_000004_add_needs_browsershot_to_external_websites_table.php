<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_websites', function (Blueprint $table) {
            // Set to true by ProbeExternalWebsiteJob when a site only exposes
            // Event JSON-LD after JavaScript has executed (Wix, Squarespace, etc.).
            // CrawlExternalWebsiteJob uses this to skip the plain-HTTP attempt and
            // go straight to Browsershot, and to enforce a per-run Browsershot cap.
            $table->boolean('needs_browsershot')->default(false)->after('blocked_reason');
        });
    }

    public function down(): void
    {
        Schema::table('external_websites', function (Blueprint $table) {
            $table->dropColumn('needs_browsershot');
        });
    }
};
