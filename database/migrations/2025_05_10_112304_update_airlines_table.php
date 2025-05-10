<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up(): void
    {
        Schema::table('airlines', function (Blueprint $table) {
            if (!Schema::hasColumn('airlines', 'icao')) {
                $table->string('icao')->nullable()->index();
            }
            if (!Schema::hasColumn('airlines', 'iata')) {
                $table->string('iata')->nullable()->index();
            }
            if (!Schema::hasColumn('airlines', 'callsign')) {
                $table->string('callsign')->nullable();
            }
            if (!Schema::hasColumn('airlines', 'country')) {
                $table->string('country')->nullable();
            }
            if (!Schema::hasColumn('airlines', 'active')) {
                $table->boolean('active')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('airlines', function (Blueprint $table) {
            $table->dropColumn(['icao', 'iata', 'callsign', 'country', 'active']);
        });
    }
};
