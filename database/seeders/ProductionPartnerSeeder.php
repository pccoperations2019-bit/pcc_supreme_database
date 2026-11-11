<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;

class ProductionPartnerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Excel::import(new \App\Imports\PartnersImport, storage_path('app/private/xlsx/2-PROGRAM-CBED-PARTNERS-ACTIVE.xlsx'));
    }
}
