<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
{
    Schema::create('airports', function (Blueprint $table) {
        $table->string('city')->nullable();
        $table->string('country')->nullable();
        $table->string('iata_code', 10)->nullable();
        $table->string('icao_code', 10)->nullable();
        $table->double('latitude')->nullable();
        $table->double('longitude')->nullable();
        $table->integer('altitude')->nullable();
        $table->double('timezone_offset')->nullable();
        $table->string('dst', 5)->nullable();
        $table->string('tz_database_timezone')->nullable();
        $table->string('airport_type')->nullable();
        $table->string('source')->nullable();
    });
}

public function down()
{
    Schema::table('airports', function (Blueprint $table) {
        $table->dropColumn([
            'city', 'country', 'iata_code', 'icao_code', 'latitude', 'longitude',
            'altitude', 'timezone_offset', 'dst', 'tz_database_timezone',
            'airport_type', 'source'
        ]);
    });
}

};
