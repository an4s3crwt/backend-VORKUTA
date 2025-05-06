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
        Schema::create('saved_flights', function (Blueprint $table) {
            $table->id();
            $table->string('user_uid'); // UID del usuario (desde Firebase)
            $table->string('flight_icao');
            $table->json('flight_data')->nullable();
            $table->timestamp('saved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_flights');
    }
};
