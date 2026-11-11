<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Center;
use Illuminate\Database\Seeder;

class CenterSeeder extends Seeder
{
    public function run(): void
    {
        $agency = Agency::first(); // Assuming AgencySeeder ran first

        $center = Center::create([
            'agency_id' => $agency->id,
            'center_code' => 'pcc-muñoz',
            'name' => 'Muñoz Livestock Center',
            'slug' => 'munoz-livestock-center',
            'description' => 'Handles livestock research and extension services.',
        ]);

        $center->address()->create([
            'street' => '123 Center St',
            'city' => 'Muñoz',
            'province' => 'Nueva Ecija',
        ]);
    }
}
