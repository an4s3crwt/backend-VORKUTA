<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('flights', function (Blueprint $table) {
            $table->id();
            $table->string('flight_number');
            $table->foreignId('airline_id')->constrained();  // Relación con aerolíneas
            $table->foreignId('departure_airport_id')->constrained('airports');  // Relación con aeropuerto de salida
            $table->foreignId('arrival_airport_id')->constrained('airports');  // Relación con aeropuerto de llegada
            $table->enum('status', ['active', 'cancelled', 'completed']);  // Estado del vuelo
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('flights');
    }

};
