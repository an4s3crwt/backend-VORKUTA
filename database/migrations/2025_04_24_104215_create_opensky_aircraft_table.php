<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */

public function up()
{
    Schema::create('opensky_aircraft', function (Blueprint $table) {
        $table->id();
        $table->string('icao24');
        $table->string('callsign')->nullable();
        $table->string('origin_country');
        $table->float('longitude')->nullable();
        $table->float('latitude')->nullable();
        $table->float('baro_altitude')->nullable();
        $table->float('velocity')->nullable();
        $table->timestamp('time_position')->nullable();
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opensky_aircraft');
    }
};
