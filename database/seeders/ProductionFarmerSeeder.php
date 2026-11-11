<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;

class ProductionFarmerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Excel::import(new \App\Imports\FarmersImport, storage_path('app/private/xlsx/2.4 PROGRAM-CBED-FARMERMEMBER.xlsx'));
    }
}
