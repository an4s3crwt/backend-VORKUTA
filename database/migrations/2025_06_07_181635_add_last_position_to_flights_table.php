<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLastPositionToFlightsTable extends Migration
{
    public function up()
    {
        Schema::table('flights', function (Blueprint $table) {
            $table->decimal('last_latitude', 10, 6)->nullable()->after('last_contact');
            $table->decimal('last_longitude', 10, 6)->nullable()->after('last_latitude');
            $table->decimal('last_altitude', 8, 2)->nullable()->after('last_longitude');
        });
    }

    public function down()
    {
        Schema::table('flights', function (Blueprint $table) {
            $table->dropColumn(['last_latitude', 'last_longitude', 'last_altitude']);
        });
    }
}
