<?php

namespace Tests\Unit;

use App\Support\RegistryActivityCell;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RegistryActivityCellTest extends TestCase
{
    #[DataProvider('activityCases')]
    public function test_it_parses_activity_cells(
        mixed $input,
        ?string $start,
        ?string $end,
        ?string $note,
    ): void {
        $parsed = RegistryActivityCell::parse($input);

        $this->assertSame($start, $parsed['start'], 'start for '.var_export($input, true));
        $this->assertSame($end, $parsed['end'], 'end for '.var_export($input, true));
        $this->assertSame($note, $parsed['note'], 'note for '.var_export($input, true));
    }

    public static function activityCases(): array
    {
        return [
            'null' => [null, null, null, null],
            'blank' => ['  ', null, null, null],
            'single date' => ['03/30/2022', '2022-03-30', null, null],
            'same month dash range' => ['03/30-31/2022', '2022-03-30', '2022-03-31', null],
            'same month dash range may' => ['05/24-25/2022', '2022-05-24', '2022-05-25', null],
            'month name range' => ['Dec. 12-13, 2022', '2022-12-12', '2022-12-13', null],
            'ampersand range' => ['11/18 & 21/2022', '2022-11-18', '2022-11-21', null],
            'to range' => ['09/29/2022 to 10/01/2022', '2022-09-29', '2022-10-01', null],
            'multiline list' => [
                "05/13/ 2022\n06/10/2022\n06/29/2022\n07/14/2022",
                '2022-05-13', '2022-07-14', null,
            ],
            'multiline trailing newline' => [
                "01/18/2022\n02/24/2022\n",
                '2022-01-18', '2022-02-24', null,
            ],
            'date with note stays in metadata' => [
                '07/28/2023 (Graduation)',
                null, null, '07/28/2023 (Graduation)',
            ],
            'soft opening stays in metadata' => [
                '2/10/2025 (Soft opening)',
                null, null, '2/10/2025 (Soft opening)',
            ],
            'status word stays in metadata' => ['Completed', null, null, 'Completed'],
            'mixed dates and text stay in metadata' => [
                "05/13/2022\nwaiting for venue",
                null, null, "05/13/2022\nwaiting for venue",
            ],
        ];
    }

    public function test_it_flags_ambiguous_order_dates(): void
    {
        $parsed = RegistryActivityCell::parse('09/12/2022');

        $this->assertSame('2022-09-12', $parsed['start']);
        $this->assertTrue($parsed['ambiguous_order']);
    }
}
