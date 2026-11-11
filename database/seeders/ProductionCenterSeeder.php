<?php

namespace Database\Seeders;

use App\Actions\Center\UpsertCenterAction;
use App\Models\Agency;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;
use League\Csv\Statement;

class ProductionCenterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(UpsertCenterAction $upsertCenter): void
    {
        // DB::transaction(function () use ($upsertCenter) {
        //     // Seed Agencies
        //     $agency = Agency::firstOrCreate(
        //         ['code' => 'PCC'],
        //         [
        //             'name' => 'Philippine Carabao Center',
        //             'description' => 'Implementing agency for the Supreme CBED project',
        //         ]
        //     );

        //     // $csv = Reader::from(storage_path('app/private/csv/centers.csv'), 'r');
        //     // $csv->setHeaderOffset(0);
        //     // $csv->setEscape(''); // required in PHP8.4+ to avoid deprecation notices

        //     // $stmt = (new Statement);
        //     // $records = $stmt->process($csv);

        //     // foreach ($records as $record) {
        //     //     $miscRecord = array_diff_key($record, array_flip([
        //     //         'CENTER CODE',
        //     //         'CENTER NAME',
        //     //         'DESCRIPTION',
        //     //     ]));

        //     //     $description = '';
        //     //     foreach ($miscRecord as $key => $value) {
        //     //         $description .= "*{$key}*: {$value}\n";
        //     //     }
        //     //     $upsertCenter->execute(
        //     //         code: $record['CENTER CODE'],
        //     //         agencyId: $agency->id,
        //     //         name: $record['CENTER NAME'],
        //     //         description: trim($description) ?: null,
        //     //         street: $record['STREET'] ?: null,
        //     //         barangay: $record['BARANGAY'] ?: null,
        //     //         city: $record['MUNICIPALITY'] ?: null,
        //     //         province: $record['PROVINCE'] ?: null,
        //     //         region: $record['REGION'] ?: null,
        //     //         district: $record['DISTRICT'] ?: null,
        //     //         zipCode: $record['ZIPCODE'] ?: null,
        //     //         latitude: null,
        //     //         longitude: null,
        //     //         markerIcon: null,
        //     //     );
        //     // }
        // });
    }
}
