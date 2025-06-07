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
    Schema::create('flights', function (Blueprint $table) {
        $table->id();
        $table->string('icao24')->index();
        $table->string('callsign')->nullable()->index();
        $table->string('origin_country')->nullable();
        
        $table->string('departure_airport')->nullable();
        $table->string('arrival_airport')->nullable();

        $table->float('departure_latitude')->nullable();
        $table->float('departure_longitude')->nullable();
        $table->float('arrival_latitude')->nullable();
        $table->float('arrival_longitude')->nullable();

        $table->unsignedBigInteger('departure_time')->nullable();
        $table->unsignedBigInteger('arrival_time')->nullable();

        $table->float('duration_expected')->nullable();
        $table->float('duration_real')->nullable();
        $table->boolean('delayed')->default(false);

        $table->boolean('last_on_ground')->nullable();
        $table->float('last_velocity')->nullable();

        $table->unsignedBigInteger('last_contact')->nullable();

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flights');
    }
};
