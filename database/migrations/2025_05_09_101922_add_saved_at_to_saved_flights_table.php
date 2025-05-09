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
        Schema::table('saved_flights', function (Blueprint $table) {
            $table->timestamp('saved_at')->nullable();  // You can set the default as null
        });
    }
    
    public function down()
    {
        Schema::table('saved_flights', function (Blueprint $table) {
            $table->dropColumn('saved_at');
        });
    }
    
};
