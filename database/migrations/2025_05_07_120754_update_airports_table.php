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
        Schema::table('airports', function (Blueprint $table) {
            // Cambiar los campos para ajustarlos a los nuevos requerimientos
            $table->string('airport');              // Nombre del aeropuerto
            $table->string('iata');                 // Código IATA
            $table->string('icao');                 // Código ICAO
            $table->string('country_code');         // Código del país
            $table->string('region_name');          // Nombre de la región
            $table->decimal('latitude', 10, 7);    // Latitud
            $table->decimal('longitude', 10, 7);   // Longitud
        });
    }
    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
