<?php

namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AirportsSeeder extends Seeder
{
    public function run()
    {
        $csvFile = storage_path('app/public/airports.dat');
        $data = array_map('str_getcsv', file($csvFile));

        foreach ($data as $row) {
            // Convierte '\N' a null
            $row = array_map(function ($value) {
                return $value === '\N' ? null : $value;
            }, $row);

            DB::table('airports')->insert([
                'city'                 => $row[2],
                'country'              => $row[3],
                'iata_code'            => $row[4],
                'icao_code'            => $row[5],
                'latitude'             => is_numeric($row[6]) ? (float) $row[6] : null,
                'longitude'            => is_numeric($row[7]) ? (float) $row[7] : null,
                'altitude'             => is_numeric($row[8]) ? (int) $row[8] : null,
                'timezone_offset'      => is_numeric($row[9]) ? (float) $row[9] : null,
                'dst'                  => $row[10],
                'tz_database_timezone' => $row[11],
                'airport_type'         => $row[12],
                'source'               => $row[13],
            ]);
        }
    }
}
