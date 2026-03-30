<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('newsletter_runs', function (Blueprint $table): void {
            $table->json('bucket_summary')->nullable()->after('fallback_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('newsletter_runs', function (Blueprint $table): void {
            $table->dropColumn('bucket_summary');
        });
    }
};
