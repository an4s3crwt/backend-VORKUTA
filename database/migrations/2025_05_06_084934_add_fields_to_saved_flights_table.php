<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_flights', function (Blueprint $table) {
            $table->string('aircraft_type')->nullable()->after('flight_icao');
            $table->string('airline_code')->nullable()->after('aircraft_type');
            $table->string('departure_airport')->nullable()->after('airline_code');
            $table->string('arrival_airport')->nullable()->after('departure_airport');
        });
    }

    public function down(): void
    {
        Schema::table('saved_flights', function (Blueprint $table) {
            $table->dropColumn(['aircraft_type', 'airline_code', 'departure_airport', 'arrival_airport']);
        });
    }
};
