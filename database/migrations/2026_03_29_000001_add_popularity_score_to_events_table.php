<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a pre-computed AI popularity score to every event.
     *
     * The score (1.0–10.0) is set by AiEventClassifier when ClassifyEventJob
     * runs during ingestion. It reflects the event's likely broad appeal —
     * a score of 9 means a major sold-out act; a score of 2 means a small
     * recurring local gathering.
     *
     * Null means the event has not yet been classified (newly ingested events
     * that haven't had their ClassifyEventJob dispatched, or events where the
     * API key is unavailable). EventMatcher falls back to a neutral 5.0 midpoint
     * for unscored events so they aren't inadvertently penalised.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->float('popularity_score', 4, 1)->nullable()->after('score_manual')
                ->comment('AI-estimated popularity 1.0–10.0; null = not yet scored');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('popularity_score');
        });
    }
};
