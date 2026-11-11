<?php

namespace Database\Seeders;

use App\Enums\ProgramEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductionServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            [
                'simplified_code' => '1.1.1',
                'mfo_code' => '1.1',
                'name' => 'Production Support Services',
                'program' => ProgramEnum::CBED->value,
                'is_title' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'simplified_code' => '1.2.3',
                'mfo_code' => '1.2',
                'name' => 'Market Development Services',
                'program' => ProgramEnum::CBED->value,
                'is_title' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'simplified_code' => '1.3.1',
                'mfo_code' => '1.3',
                'name' => 'Information, Education and communication',
                'program' => ProgramEnum::CBED->value,
                'is_title' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'simplified_code' => '1.3.5',
                'mfo_code' => '1.3',
                'name' => 'Extension support, education, and training services',
                'program' => ProgramEnum::CBED->value,
                'is_title' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('services')->upsert(
            $services,
            ['simplified_code', 'mfo_code'],
            ['name', 'program', 'is_title', 'updated_at']
        );
    }
}
