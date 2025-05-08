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
        Schema::create('flight_views', function (Blueprint $table) {
            $table->id();
            $table->string('callsign')->nullable();
            $table->string('flight_number')->nullable();
            $table->string('from_airport_code')->nullable();
            $table->string('to_airport_code')->nullable();
            $table->timestamp('viewed_at')->useCurrent();
            $table->string('firebase_uid')->nullable(); // Cambiado a string para Firebase UID
            
            // Si necesitas relación con la tabla users:
            $table->foreign('firebase_uid')->references('firebase_uid')->on('users')->onDelete('cascade');
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
