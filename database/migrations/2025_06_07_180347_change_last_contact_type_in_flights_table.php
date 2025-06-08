<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('flights', function (Blueprint $table) {
            // Cambiar tipo de last_contact a timestamp nullable
            $table->timestamp('last_contact')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('flights', function (Blueprint $table) {
            // Revertir a unsignedBigInteger si quieres deshacer la migración
            $table->unsignedBigInteger('last_contact')->nullable()->change();
        });
    }
};
