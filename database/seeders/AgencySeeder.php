<?php

namespace Database\Seeders;

use App\Models\Agency;
use Illuminate\Database\Seeder;

class AgencySeeder extends Seeder
{
    public function run(): void
    {
        $agency = Agency::create([
            'agency_code' => 'pcc',
            'name' => 'Philippine Carabao Center',
            'slug' => 'philippine-carabao-center',
            'description' => 'A leading livestock research and development agency.',
        ]);

        $agency->address()->create([
            'street' => 'Science City Rd',
            'city' => 'Science City of Muñoz',
            'province' => 'Nueva Ecija',
        ]);
    }
}
