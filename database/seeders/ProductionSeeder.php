<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductionSeeder extends Seeder
{
    const AGENCY_CODE = 'PCC';

    const CENTER_CODE = 'SUPREME';

    const AGENCY_USER01 = 'pcc-admin@gmail.com';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            RolesPermissionsSeeder::class,
            ProductionCenterSeeder::class,
            ProductionHerdSeeder::class,
            ProductionPartnerSeeder::class,
            ProductionUserSeeder::class,
            ProductionCenterUserSeeder::class,
            ProductionServiceSeeder::class,
        ]);
    }
}
