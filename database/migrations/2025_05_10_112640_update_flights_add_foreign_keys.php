<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
    {
        Schema::table('flights', function (Blueprint $table) {
            // Añadir claves foráneas (nullable y con onDelete set null)
            if (!Schema::hasColumn('flights', 'airline_id')) {
                $table->foreignId('airline_id')->nullable()->constrained()->nullOnDelete();
            }

            if (!Schema::hasColumn('flights', 'departure_airport_id')) {
                $table->foreignId('departure_airport_id')->nullable()->constrained('airports')->nullOnDelete();
            }

            if (!Schema::hasColumn('flights', 'arrival_airport_id')) {
                $table->foreignId('arrival_airport_id')->nullable()->constrained('airports')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('flights', function (Blueprint $table) {
            $table->dropForeign(['airline_id']);
            $table->dropForeign(['departure_airport_id']);
            $table->dropForeign(['arrival_airport_id']);
            $table->dropColumn(['airline_id', 'departure_airport_id', 'arrival_airport_id']);
        });
    }
};
