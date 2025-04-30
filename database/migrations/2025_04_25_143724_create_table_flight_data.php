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
            Schema::create('flight_data', function (Blueprint $table) {
                $table->id();
                $table->string('icao')->index();
                $table->string('callsign')->index();
                $table->float('latitude');
                $table->float('longitude');
                $table->float('altitude');
                $table->float('speed');
                $table->timestamps();
            });
        }
    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flight_data');

    }
};
