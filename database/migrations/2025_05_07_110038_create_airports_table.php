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
        Schema::create('airports', function (Blueprint $table) {
            $table->id(); // id auto incremental, tipo unsignedBigInteger
            $table->string('name'); // nombre del aeropuerto
            $table->string('code'); // código del aeropuerto, como IATA o ICAO
            $table->string('country'); // país del aeropuerto
            $table->timestamps(); // marca de tiempo para created_at y updated_at
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('airports');
    }
    

   
};
