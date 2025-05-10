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
        Schema::table('flights', function (Blueprint $table) {
            if (!Schema::hasColumn('flights', 'callsign')) {
                $table->string('callsign')->nullable()->index();
            }

            if (!Schema::hasColumn('flights', 'icao24')) {
                $table->string('icao24')->nullable()->index();
            }

            if (!Schema::hasColumn('flights', 'airline')) {
                $table->string('airline')->nullable();
            }

            if (!Schema::hasColumn('flights', 'departure_airport')) {
                $table->string('departure_airport')->nullable();
            }

            if (!Schema::hasColumn('flights', 'arrival_airport')) {
                $table->string('arrival_airport')->nullable();
            }

            if (!Schema::hasColumn('flights', 'last_seen')) {
                $table->timestamp('last_seen')->nullable();
            }

            if (!Schema::hasColumn('flights', 'status')) {
                $table->string('status')->default('active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flights', function (Blueprint $table) {
            $table->dropColumn([
                'callsign',
                'icao24',
                'airline',
                'departure_airport',
                'arrival_airport',
                'last_seen',
                'status',
            ]);
        });
    }
};
