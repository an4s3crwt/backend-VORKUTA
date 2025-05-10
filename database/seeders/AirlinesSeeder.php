<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AirlinesSeeder extends Seeder
{
    public function run()
    {
        $csvFile = storage_path('app/public/airlines.dat');
        $data = array_map('str_getcsv', file($csvFile));

        foreach ($data as $row) {
            // Normaliza los valores
            $row = array_map(function ($value) {
                return $value === '\N' ? null : $value;
            }, $row);

            DB::table('airlines')->insert([
                'alias'     => $row[2],
                'iata_code' => $row[3],
                'icao_code' => $row[4],
                'callsign'  => $row[5],
                'country'   => $row[6],
                'active'    => ($row[7] ?? 'N') === 'Y' ? 1 : 0,
            ]);
        }
    }
}
