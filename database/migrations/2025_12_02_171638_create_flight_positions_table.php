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
    Schema::create('flight_positions', function (Blueprint $table) {
        $table->id();
        $table->string('icao24')->index(); 
        
        // 👇 AHORA TODO ACEPTA NULOS (NULLABLE)
        $table->float('latitude')->nullable();
        $table->float('longitude')->nullable();
        $table->float('velocity')->nullable();
        $table->float('heading')->nullable();
        
        $table->float('baro_altitude')->nullable(); // ✅ El culpable arreglado
        $table->float('geo_altitude')->nullable();
        
        $table->boolean('on_ground')->default(false); // Por defecto en el aire
        $table->float('vertical_rate')->nullable();
        
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->useCurrent();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flight_positions');
    }
};
