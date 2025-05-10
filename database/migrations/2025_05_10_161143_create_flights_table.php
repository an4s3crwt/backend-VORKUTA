<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
{
    Schema::create('flights', function (Blueprint $table) {
        if (!Schema::hasColumn('flights', 'airline_name')) {
            $table->string('airline_name')->nullable();
        }
        if (!Schema::hasColumn('flights', 'airline_number')) {
            $table->string('airline_number')->nullable();
        }
        if (!Schema::hasColumn('flights', 'flight_number')) {
            $table->string('flight_number')->nullable();
        }
        if (!Schema::hasColumn('flights', 'departure_time')) {
            $table->timestamp('departure_time')->nullable();
        }
        if (!Schema::hasColumn('flights', 'arrival_time')) {
            $table->timestamp('arrival_time')->nullable();
        }
        if (!Schema::hasColumn('flights', 'duration')) {
            $table->integer('duration')->nullable();
        }
        if (!Schema::hasColumn('flights', 'stops')) {
            $table->integer('stops')->nullable();
        }
        if (!Schema::hasColumn('flights', 'price')) {
            $table->decimal('price', 8, 2)->nullable();
        }
        if (!Schema::hasColumn('flights', 'currency')) {
            $table->string('currency')->nullable();
        }
        if (!Schema::hasColumn('flights', 'co2_emissions')) {
            $table->decimal('co2_emissions', 8, 2)->nullable();
        }
        if (!Schema::hasColumn('flights', 'avg_co2_emission_for_this_route')) {
            $table->decimal('avg_co2_emission_for_this_route', 8, 2)->nullable();
        }
        if (!Schema::hasColumn('flights', 'co2_percentage')) {
            $table->decimal('co2_percentage', 5, 2)->nullable();
        }
        if (!Schema::hasColumn('flights', 'scan_date')) {
            $table->timestamp('scan_date')->nullable();
        }
    });
}
public function down()
{
    Schema::table('flights', function (Blueprint $table) {
        if (Schema::hasColumn('flights', 'airline_name')) {
            $table->dropColumn('airline_name');
        }
        if (Schema::hasColumn('flights', 'airline_number')) {
            $table->dropColumn('airline_number');
        }
        if (Schema::hasColumn('flights', 'flight_number')) {
            $table->dropColumn('flight_number');
        }
        if (Schema::hasColumn('flights', 'departure_time')) {
            $table->dropColumn('departure_time');
        }
        if (Schema::hasColumn('flights', 'arrival_time')) {
            $table->dropColumn('arrival_time');
        }
        if (Schema::hasColumn('flights', 'duration')) {
            $table->dropColumn('duration');
        }
        if (Schema::hasColumn('flights', 'stops')) {
            $table->dropColumn('stops');
        }
        if (Schema::hasColumn('flights', 'price')) {
            $table->dropColumn('price');
        }
        if (Schema::hasColumn('flights', 'currency')) {
            $table->dropColumn('currency');
        }
        if (Schema::hasColumn('flights', 'co2_emissions')) {
            $table->dropColumn('co2_emissions');
        }
        if (Schema::hasColumn('flights', 'avg_co2_emission_for_this_route')) {
            $table->dropColumn('avg_co2_emission_for_this_route');
        }
        if (Schema::hasColumn('flights', 'co2_percentage')) {
            $table->dropColumn('co2_percentage');
        }
        if (Schema::hasColumn('flights', 'scan_date')) {
            $table->dropColumn('scan_date');
        }
    });
}
    
};
