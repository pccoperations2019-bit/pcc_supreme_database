<?php

namespace Database\Seeders;

use App\Actions\Herd\UpsertHerdAction;
use App\Models\Agency;
use App\Models\Center;
use App\Support\Sanitizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;
use League\Csv\Statement;

class ProductionHerdSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(UpsertHerdAction $upsertHerd): void
    {
        DB::transaction(function () use ($upsertHerd) {
            // Seed Agencies
            $agency = Agency::firstOrCreate(
                ['code' => 'PCC'],
                [
                    'name' => 'Philippine Carabao Herd',
                    'description' => 'Implementing agency for the Supreme CBED project',
                ]
            );

            $csv = Reader::from(storage_path('app/private/csv/herds.csv'), 'r');
            $csv->setHeaderOffset(0);
            $csv->setEscape(''); // required in PHP8.4+ to avoid deprecation notices

            $stmt = (new Statement);
            $records = $stmt->process($csv);

            $centers = Center::all()->keyBy('code');

            foreach ($records as $record) {
                $miscRecord = array_diff_key($record, array_flip([
                    'IHCODE',
                    'CENTER NAME',
                    'DESCRIPTION',
                ]));

                $description = '';
                foreach ($miscRecord as $key => $value) {
                    $description .= "*{$key}*: {$value}\n";
                }
                $upsertHerd->execute(
                    code: $record['IHCODE'],
                    agencyId: $agency->id,
                    centerId: $centers->get($record['CENTER CODE'])->id ?? null,
                    name: $record['CENTER NAME'],
                    description: trim($description) ?: null,
                    street: $record['STREET'] ?: null,
                    barangay: $record['BARANGAY'] ?: null,
                    city: $record['MUNICIPALITY'] ?: null,
                    province: $record['PROVINCE'] ?: null,
                    region: $record['REGION'] ?: null,
                    district: $record['DISTRICT'] ?: null,
                    zipCode: isset($record['ZIPCODE']) && Sanitizer::parseNumeric($record['ZIPCODE']) ?: null
                );
            }
        });
    }
}
