<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Concerns\HasSelectableOptions;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;
use App\Enums\Contracts\WithSelectableOptions;

enum AddressRegionEnum: string implements WithLabels, WithMatches, WithSelectableOptions
{
    use HasLabels;
    use HasMatches;
    use HasSelectableOptions;

    case REGION_I = 'I';
    case REGION_II = 'II';
    case REGION_III = 'III';
    case REGION_IV_A = 'IV-A';
    case REGION_IV_B = 'IV-B';
    case REGION_V = 'V';
    case REGION_VI = 'VI';
    case REGION_VII = 'VII';
    case REGION_VIII = 'VIII';
    case REGION_IX = 'IX';
    case REGION_X = 'X';
    case REGION_XI = 'XI';
    case REGION_XII = 'XII';
    case NCR = 'NCR';
    case CAR = 'CAR';
    case BARMM = 'BARMM';
    case REGION_XIII = 'XIII';
    case NIR = 'NIR';

    public static function labels(): array
    {
        return [
            self::REGION_I->value => 'Region I (Ilocos Region)',
            self::REGION_II->value => 'Region II (Cagayan Valley)',
            self::REGION_III->value => 'Region III (Central Luzon)',
            self::REGION_IV_A->value => 'Region IV-A (CALABARZON)',
            self::REGION_IV_B->value => 'Region IV-B (MIMAROPA)',
            self::REGION_V->value => 'Region V (Bicol Region)',
            self::REGION_VI->value => 'Region VI (Western Visayas)',
            self::REGION_VII->value => 'Region VII (Central Visayas)',
            self::REGION_VIII->value => 'Region VIII (Eastern Visayas)',
            self::REGION_IX->value => 'Region IX (Zamboanga Peninzula)',
            self::REGION_X->value => 'Region X (Northern Mindanao)',
            self::REGION_XI->value => 'Region XI (Davao Region)',
            self::REGION_XII->value => 'Region XII (SOCCSKSARGEN)',
            self::NCR->value => 'National Capital Region (NCR)',
            self::CAR->value => 'Cordillera Administrative Region (CAR)',
            self::BARMM->value => 'Autonomous Region in Muslim Mindanao (BARMM)',
            self::REGION_XIII->value => 'Region XIII (Caraga)',
            self::NIR->value => 'Negros Island Region (NIR)',
        ];
    }

    public static function matches(): array
    {
        return [
            self::REGION_I->value => ['Region I (Ilocos Region)', 'Ilocos Region', 'I', 1, 'Region I', 'REGION I', 'Region 1', 'REGION 1', 'REGION I (ILOCOS REGION)', 'Region 01', 'REGION 01', 'R1', 'R-1'],
            self::REGION_II->value => ['Region II (Cagayan Valley)', 'Cagayan Valley', 'II', 2, 'Region II', 'REGION II', 'Region 2', 'REGION 2', 'REGION II (CAGAYAN VALLEY)', 'Region 02', 'REGION 02', 'R2', 'R-2'],
            self::REGION_III->value => ['Region III (Central Luzon)', 'Central Luzon', 'III', 3, 'Region III', 'REGION III', 'Region 3', 'REGION 3', 'REGION III (CENTRAL LUZON)', 'Region 03', 'REGION 03', 'R3', 'R-3'],
            self::REGION_IV_A->value => ['Region IV-A (CALABARZON)', 'CALABARZON', 'IV-A', 'IVA', 4, 'Region IV-A', 'REGION IV-A', 'Region IVA', 'REGION IVA', 'Region 4-A', 'REGION 4-A', 'Region 4A', 'REGION 4A', 'REGION IV A', 'Region IV A', 'Region 04-A', 'REGION 04-A', 'R4A', 'R-4A', 'IV A'],
            self::REGION_IV_B->value => ['Region IV-B (MIMAROPA)', 'MIMAROPA', 'IV-B', 'IVB', 'Region IVB', 5, 'Region IV-B', 'REGION IV-B', 'Region 4-B', 'REGION 4-B', 'Region 4B', 'REGION 4B', 'REGION IV B', 'Region IV B', 'Region 04-B', 'REGION 04-B', 'R4B', 'R-4B', 'IV B'],
            self::REGION_V->value => ['Region V (Bicol Region)', 'Bicol Region', 'V', 6, 'Region V', 'REGION V', 'Region 5', 'REGION 5', 'REGION V (BICOL REGION)', 'Region 05', 'REGION 05', 'R5', 'R-5'],
            self::REGION_VI->value => ['Region VI (Western Visayas)', 'Western Visayas', 'VI', 7, 'Region VI', 'REGION VI', 'Region 6', 'REGION 6', 'REGION VI (WESTERN VISAYAS)', 'Region 06', 'REGION 06', 'R6', 'R-6'],
            self::REGION_VII->value => ['Region VII (Central Visayas)', 'Central Visayas', 'VII', 8, 'Region VII', 'REGION VII', 'Region 7', 'REGION 7', 'REGION VII (CENTRAL VISAYAS)', 'Region 07', 'REGION 07', 'R7', 'R-7'],
            self::REGION_VIII->value => ['Region VIII (Eastern Visayas)', 'Eastern Visayas', 'VIII', 9, 'Region VIII', 'REGION VIII', 'Region 8', 'REGION 8', 'REGION VIII (EASTERN VISAYAS)', 'Region 08', 'REGION 08', 'R8', 'R-8'],
            self::REGION_IX->value => ['Region IX (Zamboanga Peninzula)', 'Zamboanga Peninzula', 'IX', 10, 'Region IX', 'REGION IX', 'Region 9', 'REGION 9', 'REGION IX (ZAMBOANGA PENINZULA)', 'Region 09', 'REGION 09', 'R9', 'R-9'],
            self::REGION_X->value => ['Region X (Northern Mindanao)', 'Northern Mindanao', 'X', 11, 'Region X', 'REGION X', 'Region 10', 'REGION 10', 'REGION X (NORTHERN MINDANAO)', 'Region 010', 'REGION 010', 'R10', 'R-10'],
            self::REGION_XI->value => ['Region XI (Davao Region)', 'Davao Region', 'XI', 12, 'Region XI', 'REGION XI', 'Region 11', 'REGION 11', 'REGION XI (DAVAO REGION)', 'Region 011', 'REGION 011', 'R11', 'R-11'],
            self::REGION_XII->value => ['Region XII (SOCCSKSARGEN)', 'SOCCSKSARGEN', 'XII', 13, 'Region XII', 'REGION XII', 'Region 12', 'REGION 12', 'REGION XII (SOCCSKSARGEN)', 'Region 012', 'REGION 012', 'R12', 'R-12'],
            self::NCR->value => ['National Capital Region (NCR)', 'NCR', 'NCR', 14, 'National Capital Region', 'REGION NCR', 'Region NCR'],
            self::CAR->value => ['Cordillera Administrative Region (CAR)', 'CAR', 'CAR', 15, 'Cordillera Administrative Region', 'REGION CAR', 'Region CAR'],
            self::BARMM->value => ['Autonomous Region in Muslim Mindanao (BARMM)', 'BARMM', 'BARMM', 16, 'Bangsamoro', 'Bangsamoro Autonomous Region in Muslim Mindanao', 'REGION BARMM', 'Region BARMM'],
            self::REGION_XIII->value => ['Region XIII (Caraga)', 'Caraga', 'CARAGA', 'XIII', 17, 'Region XIII', 'REGION XIII', 'Region 13', 'REGION 13', 'REGION XIII (CARAGA)', 'Region 013', 'REGION 013', 'R13', 'R-13'],
            self::NIR->value => ['Negros Island Region (NIR)', 'NIR', 'Negros Island Region', 18, 'Region NIR', 'REGION NIR', 'Negros Island'],
        ];
    }

    public static function sortOrder(): array
    {
        return [
            self::REGION_I->value => 1,
            self::REGION_II->value => 2,
            self::REGION_III->value => 3,
            self::REGION_IV_A->value => 4,
            self::REGION_IV_B->value => 5,
            self::REGION_V->value => 6,
            self::REGION_VI->value => 7,
            self::REGION_VII->value => 8,
            self::REGION_VIII->value => 9,
            self::REGION_IX->value => 10,
            self::REGION_X->value => 11,
            self::REGION_XI->value => 12,
            self::REGION_XII->value => 13,
            self::NCR->value => 14,
            self::CAR->value => 15,
            self::BARMM->value => 16,
            self::REGION_XIII->value => 17,
            self::NIR->value => 18,
        ];
    }

    public static function sortValue(string $region): int
    {
        $normalized = strtoupper(trim($region));
        $case =
            self::tryFroMatches($region)
            ?? self::tryFroMatches($normalized)
            ?? self::fromLabel($region)
            ?? self::fromLabel($normalized)
            ?? self::tryFrom($region)
            ?? self::tryFrom($normalized);

        if (! $case) {
            return PHP_INT_MAX;
        }

        return self::sortOrder()[$case->value] ?? PHP_INT_MAX;
    }

    public static function fromPsgcCode(string $code): ?self
    {
        $digits = preg_replace('/\D/', '', $code);
        if ($digits === '') {
            return null;
        }

        // Spreadsheet exports may remove the leading zero from the 10-digit code.
        $code = strlen($digits) === 9 ? str_pad($digits, 10, '0', STR_PAD_LEFT) : $digits;

        return match ($code) {
            '0100000000' => self::REGION_I,
            '0200000000' => self::REGION_II,
            '0300000000' => self::REGION_III,
            '0400000000' => self::REGION_IV_A,
            '1700000000' => self::REGION_IV_B,
            '0500000000' => self::REGION_V,
            '0600000000' => self::REGION_VI,
            '0700000000' => self::REGION_VII,
            '0800000000' => self::REGION_VIII,
            '0900000000' => self::REGION_IX,
            '1000000000' => self::REGION_X,
            '1100000000' => self::REGION_XI,
            '1200000000' => self::REGION_XII,
            '1300000000' => self::NCR,
            '1400000000' => self::CAR,
            '1500000000', '1900000000' => self::BARMM,
            '1600000000' => self::REGION_XIII,
            '1800000000' => self::NIR,
            default => null,
        };
    }
}
