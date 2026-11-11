<?php

namespace Tests\Unit;

use App\Support\RegistryStrictDate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RegistryStrictDateTest extends TestCase
{
    #[DataProvider('acceptedCases')]
    public function test_it_parses_entire_string_dates(mixed $input, string $expected, bool $ambiguous): void
    {
        $parsed = RegistryStrictDate::parse($input);

        $this->assertNotNull($parsed, 'expected a date for '.var_export($input, true));
        $this->assertSame($expected, $parsed['date']);
        $this->assertSame($ambiguous, $parsed['ambiguous_order']);
    }

    public static function acceptedCases(): array
    {
        return [
            'us date' => ['03/30/2022', '2022-03-30', false],
            'us date padded spaces' => ['  03/30/2022  ', '2022-03-30', false],
            'space before year' => ['05/13/ 2022', '2022-05-13', false],
            'iso date' => ['2022-03-30', '2022-03-30', false],
            'month name' => ['Dec. 12, 2022', '2022-12-12', false],
            'month name no period' => ['August 16, 2022', '2022-08-16', false],
            'datetime with time' => ['08/25/2023 10:00 AM', '2023-08-25', false],
            'ambiguous both under twelve' => ['08/08/2022', '2022-08-08', true],
            'ambiguous month day swap' => ['09/12/2022', '2022-09-12', true],
            'excel serial int' => [44974, '2023-02-17', false],
            'excel serial float string' => ['44974', '2023-02-17', false],
            'datetime object' => [new \DateTimeImmutable('2022-03-30'), '2022-03-30', false],
        ];
    }

    #[DataProvider('rejectedCases')]
    public function test_it_rejects_anything_that_is_not_entirely_a_date(mixed $input): void
    {
        $this->assertNull(
            RegistryStrictDate::parse($input),
            'expected null for '.var_export($input, true)
        );
    }

    public static function rejectedCases(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'blank' => ['   '],
            'sentence with date' => ['Construction started August 16, 2022'],
            'date with note' => ['2/10/2025 (Soft opening)'],
            'graduation note' => ['07/28/2023 (Graduation)'],
            'waiting with date' => ['Waiting for NOA and NTP (12/18/25)'],
            'waiting installment' => ['still waiting for the installment of water and current 12/16/2024'],
            'status word' => ['Completed'],
            'status installed' => ['Installed'],
            'status delivered' => ['delivered'],
            'ongoing' => ['On-going'],
            'terminated' => ['Terminated contract'],
            'range' => ['03/30-31/2022'],
            'to range' => ['09/29/2022 to 10/01/2022'],
            'multi date list' => ["05/13/2022\n06/10/2022"],
            'year float is not a serial' => [2019.0],
            'year string is not a serial' => ['2019.0'],
            'month above twelve' => ['13/05/2022'],
            'impossible day' => ['02/30/2022'],
            'non date array' => [['2022-03-30']],
            'boolean' => [true],
        ];
    }
}
