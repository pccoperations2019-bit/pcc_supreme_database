<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        if (app()->environment('production')) {
            $this->call([
                RolesPermissionsSeeder::class,
                ProductionSeeder::class,
            ]);

            return;
        }

        $this->call([
            RolesPermissionsSeeder::class,
            AgencySeeder::class,
            CenterSeeder::class,
            PartnerSeeder::class,
            FarmerSeeder::class,
        ]);
    }
}
