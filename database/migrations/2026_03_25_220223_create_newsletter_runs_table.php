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
        Schema::create('newsletter_runs', function (Blueprint $table) {
            $table->id();
            $table->dateTime('scheduled_for');
            $table->dateTime('sent_at')->nullable();
            $table->string('status');
            $table->timestamps();

            $table->index(['status', 'scheduled_for']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('newsletter_runs');
    }
};
