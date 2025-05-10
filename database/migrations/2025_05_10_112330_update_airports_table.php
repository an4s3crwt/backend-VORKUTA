<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('airports', function (Blueprint $table) {
            if (!Schema::hasColumn('airports', 'icao')) {
                $table->string('icao')->nullable()->index();
            }
            if (!Schema::hasColumn('airports', 'iata')) {
                $table->string('iata')->nullable()->index();
            }
            if (!Schema::hasColumn('airports', 'city')) {
                $table->string('city')->nullable();
            }
            if (!Schema::hasColumn('airports', 'country')) {
                $table->string('country')->nullable();
            }
            if (!Schema::hasColumn('airports', 'latitude')) {
                $table->decimal('latitude', 10, 6)->nullable();
            }
            if (!Schema::hasColumn('airports', 'longitude')) {
                $table->decimal('longitude', 10, 6)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('airports', function (Blueprint $table) {
            $table->dropColumn(['icao', 'iata', 'city', 'country', 'latitude', 'longitude']);
        });
    }
};
