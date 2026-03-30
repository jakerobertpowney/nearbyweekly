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
        Schema::table('users', function (Blueprint $table) {
            $table->string('postcode')->nullable()->after('email');
            $table->decimal('latitude', 10, 7)->nullable()->after('postcode');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedSmallInteger('radius_miles')->default(25)->after('longitude');
            $table->boolean('newsletter_enabled')->default(true)->after('radius_miles');

            $table->index('postcode');
            $table->index('newsletter_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['postcode']);
            $table->dropIndex(['newsletter_enabled']);
            $table->dropColumn([
                'postcode',
                'latitude',
                'longitude',
                'radius_miles',
                'newsletter_enabled',
            ]);
        });
    }
};
