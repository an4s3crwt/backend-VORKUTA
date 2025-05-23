<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
{
    Schema::create('airlines', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('iata')->nullable();
        $table->string('icao')->nullable();
        $table->timestamps();
    });
}

public function down()
{
    Schema::dropIfExists('airlines');
}
};
