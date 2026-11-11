<?php

namespace Database\Seeders;

use App\Models\Farmer;
use App\Models\Partner;
use Illuminate\Database\Seeder;

class FarmerSeeder extends Seeder
{
    public function run(): void
    {
        $partner = Partner::first(); // Assuming PartnerSeeder ran first

        $farmer = Farmer::create([
            'partner_id' => $partner->id,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'slug' => 'juan-dela-cruz',
            'farmer_code' => 'FARM-001',

        ]);

        $farmer->address()->create([
            'street' => '789 Farm Rd',
            'city' => 'Muñoz',
            'province' => 'Nueva Ecija',
        ]);
    }
}
