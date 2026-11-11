<?php

namespace Database\Seeders;

use App\Models\Center;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // attach centers product
        //
        Center::all()->each(function ($center) {
            $center->products()->attach([
                1,
                2,
                3,
                4,
                5,
            ]);
            $center->services()->attach([
                1,
                2,
                3,
                4,
                5,
            ]);
            $center->projects()->attach([
                1,
                2,
                3,
                4,
                5,
            ]);
        });
    }
}
