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
    Schema::table('logs', function (Blueprint $table) {
        $table->string('level')->nullable(); // O el tipo adecuado para tu caso
    });
}

public function down()
{
    Schema::table('logs', function (Blueprint $table) {
        $table->dropColumn('level');
    });
}

};
