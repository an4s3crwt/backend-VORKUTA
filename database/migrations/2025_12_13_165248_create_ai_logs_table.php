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
    Schema::create('ai_logs', function (Blueprint $table) {
        $table->id();
        $table->string('flight_icao');
        $table->string('prediction'); // 'delayed', 'on_time', etc.
        $table->float('probability'); // 0.95
        $table->float('delay_minutes')->default(0);
        $table->text('reason')->nullable(); // El output de texto del modelo
        $table->timestamp('created_at')->useCurrent();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_logs');
    }
};
