<?php

namespace Tests\Feature\Exports;

use App\Exports\MilkUtilizationExport;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Reports\InteractsWithMilkUtilization;
use Tests\TestCase;

class MilkUtilizationExportTest extends TestCase
{
    use InteractsWithMilkUtilization;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMilkUtilization();
    }

    #[Test]
    public function it_passes_the_rows_through_untouched(): void
    {
        $rows = [
            ["PCC Assisted and Impact Zone Milk Prod'n Utilization Update — Coverage: January to January 2025"],
            ['Center', 'No. of Lactating Animals'],
            ['CSU', '16.00'],
        ];

        $this->assertSame($rows, (new MilkUtilizationExport($rows))->array());
    }

    #[Test]
    public function it_names_the_sheet_milk_utilization(): void
    {
        $this->assertSame('Milk Utilization', (new MilkUtilizationExport([]))->title());
    }

    #[Test]
    public function the_cbed_export_includes_center_subtotals_and_enterprise_rows(): void
    {
        $csu = $this->muCenter('CSU');
        $coop = $this->muPartner($csu, 'Test Dairy Cooperative');

        $this->muProduction($csu, 2023, 3, [
            'program' => 'cbed',
            'partner_id' => null,
            'total_volume_produced' => 50,
        ]);
        $this->muProduction($csu, 2023, 3, [
            'program' => 'cbed',
            'partner_id' => $coop,
            'total_volume_produced' => 30,
        ]);

        $rows = $this->csvRows($this->exportCsv([
            'year' => 2023,
            'month' => 3,
            'section' => 'cbed',
        ]));

        $header = $rows[1];
        $csu = $this->rowStartingWith($rows, 'CSU');
        $enterprise = $this->rowStartingWith($rows, '    Test Dairy Cooperative');

        $this->assertSame('Center', $header[0], 'The Center column leads the header row.');
        $this->assertNotSame([], $csu, 'The CSU center row is exported.');

        $production = array_search("Prod'n", $header, true);
        $this->assertNotFalse($production);
        $this->assertSame(80.0, (float) $csu[$production], 'The center subtotal folds in the enterprise record.');
        $this->assertNotSame([], $enterprise, 'The enterprise row is exported under its center.');
        $this->assertSame(30.0, (float) $enterprise[$production]);
    }

    #[Test]
    public function the_institutional_export_leads_with_the_center_column(): void
    {
        $csu = $this->muCenter('CSU');

        $this->muProduction($csu, 2023, 3, ['total_volume_produced' => 100]);

        $rows = $this->csvRows($this->exportCsv([
            'year' => 2023,
            'month' => 3,
            'section' => 'institutional',
        ]));

        $header = $rows[1];
        $csu = $this->rowStartingWith($rows, 'CSU');

        $this->assertSame('Center', $header[0], 'The Center column leads the header row.');
        $this->assertNotSame([], $csu, 'The CSU center row is exported.');

        $production = array_search("Prod'n", $header, true);
        $this->assertNotFalse($production);
        $this->assertSame(100.0, (float) $csu[$production]);
    }

    private function csvRows(string $csv): array
    {
        return array_values(array_map(
            'str_getcsv',
            preg_split('/\r\n|\n|\r/', trim($csv)) ?: [],
        ));
    }

    private function rowStartingWith(array $rows, string $firstCell): array
    {
        foreach ($rows as $row) {
            if (($row[0] ?? null) === $firstCell) {
                return $row;
            }
        }

        return [];
    }

    private function exportCsv(array $payload): string
    {

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $response = $this->actingAs(User::factory()->create())->postJson(
            route('reports.milk-utilization.export'),
            array_merge(['format' => 'csv', 'queue_export' => false], $payload),
        );

        $response->assertOk();

        $file = $response->baseResponse->getFile();

        return (string) file_get_contents($file->getPathname());
    }
}
