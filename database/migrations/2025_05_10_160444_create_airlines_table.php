<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
{
    Schema::create('airlines', function (Blueprint $table) {
        $table->string('alias')->nullable();
        $table->string('iata_code', 10)->nullable();
        $table->string('icao_code', 10)->nullable();
        $table->string('callsign')->nullable();
        $table->string('country')->nullable();
        $table->boolean('active')->default(true);
    });
}

public function down()
{
    Schema::table('airlines', function (Blueprint $table) {
        $table->dropColumn([
            'alias', 'iata_code', 'icao_code', 'callsign', 'country', 'active'
        ]);
    });
}

};
