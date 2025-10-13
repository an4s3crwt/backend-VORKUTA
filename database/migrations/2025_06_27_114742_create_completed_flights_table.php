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
    Schema::create('completed_flights', function (Blueprint $table) {
        $table->id();
        $table->string('icao24');
        $table->string('callsign')->nullable();
        $table->string('origin_country')->nullable();

        $table->timestamp('departure_time')->nullable();
        $table->decimal('departure_latitude', 10, 7)->nullable();
        $table->decimal('departure_longitude', 10, 7)->nullable();

        $table->timestamp('arrival_time')->nullable();
        $table->decimal('arrival_latitude', 10, 7)->nullable();
        $table->decimal('arrival_longitude', 10, 7)->nullable();

        $table->integer('duration_real')->nullable(); // segundos
        $table->integer('duration_expected')->nullable(); // segundos
        $table->boolean('delayed')->default(false);

        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('completed_flights');
    }
};
