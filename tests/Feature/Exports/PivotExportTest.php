<?php

namespace Tests\Feature\Exports;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class PivotExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_uses_field_labels_as_headers(): void
    {
        $user = User::factory()->create([
            'username' => 'user-'.Str::uuid(),
        ]);

        $agencyId = DB::table('agencies')->insertGetId([
            'code' => 'AGT-EXP',
            'name' => 'Export Agency',
        ]);

        $centerId = DB::table('centers')->insertGetId([
            'code' => 'EXPC',
            'name' => 'Export Center',
            'agency_id' => $agencyId,
            'status' => 'active',
        ]);

        DB::table('addresses')->insert([
            'addressable_type' => 'center',
            'addressable_id' => $centerId,
            'region' => 'I',
        ]);

        $response = $this->actingAs($user)->get('/pivot/export?'.http_build_query([
            'source' => 'center',
            'rows' => 'code,region',
            'columns' => '',
            'values' => json_encode([
                ['field' => 'code', 'agg' => 'count'],
            ]),
            'filters' => json_encode([]),
        ]));

        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'pivot').'.xlsx';

        file_put_contents($path, $response->streamedContent());

        $sheet = IOFactory::load($path)->getActiveSheet();

        $this->assertSame('Center code', $sheet->getCell('A1')->getValue());
        $this->assertSame('Region', $sheet->getCell('B1')->getValue());
        $this->assertSame('EXPC', $sheet->getCell('A2')->getValue());
        $this->assertSame('Region I (Ilocos Region)', $sheet->getCell('B2')->getValue());

        unlink($path);
    }
}
