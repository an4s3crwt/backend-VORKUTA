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
        Schema::table('saved_flights', function (Blueprint $table) {
            $table->string('firebase_uid')->nullable()->after('user_id');

            // Si quieres agregar una clave foránea referenciando la tabla users:
            $table->foreign('firebase_uid')
                  ->references('firebase_uid')
                  ->on('users')
                  ->onDelete('cascade'); // O set null, restrict, etc.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('saved_flights', function (Blueprint $table) {
            $table->dropForeign(['firebase_uid']);
            $table->dropColumn('firebase_uid');
        });
    }
};
