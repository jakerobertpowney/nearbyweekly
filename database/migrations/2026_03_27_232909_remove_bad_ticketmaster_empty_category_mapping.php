<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('category_mappings')
            ->where('source', 'ticketmaster')
            ->where('external_category', '')
            ->delete();

        DB::table('category_mappings')
            ->where('source', 'ticketmaster')
            ->whereIn('external_category', ['undefined', 'miscellaneous'])
            ->where('ai_generated', true)
            ->delete();
    }

    public function down(): void
    {
        // Non-reversible data cleanup — no rollback needed
    }
};
