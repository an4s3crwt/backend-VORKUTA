<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void {
        Schema::create('performance_logs', function (Blueprint $table) {
            $table->id();
            $table->string('method');
            $table->string('path');
            $table->float('response_time'); // en segundos
            $table->string('status_code');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('performance_logs');
    }
};
