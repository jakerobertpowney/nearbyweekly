<?php

use App\Models\Interest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interests', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('interests')
                ->nullOnDelete()
                ->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('interests', function (Blueprint $table) {
            $table->dropForeignIdFor(Interest::class, 'parent_id');
            $table->dropColumn('parent_id');
        });
    }
};
