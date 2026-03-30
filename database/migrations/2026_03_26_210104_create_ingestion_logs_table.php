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
        Schema::create('ingestion_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('status'); // success | failed
            $table->integer('fetched')->default(0);
            $table->integer('created')->default(0);
            $table->integer('updated')->default(0);
            $table->integer('skipped')->default(0);
            $table->integer('failed')->default(0);
            $table->dateTime('ran_at');
            $table->timestamps();

            $table->index(['provider', 'ran_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingestion_logs');
    }
};
