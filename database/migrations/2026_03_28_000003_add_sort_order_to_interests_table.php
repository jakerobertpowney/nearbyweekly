<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interests', function (Blueprint $table): void {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('interests', function (Blueprint $table): void {
            $table->dropColumn('sort_order');
        });
    }
};
