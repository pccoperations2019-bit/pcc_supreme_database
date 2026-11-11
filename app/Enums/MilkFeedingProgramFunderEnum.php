<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Concerns\HasSelectableOptions;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;
use App\Enums\Contracts\WithSelectableOptions;

enum MilkFeedingProgramFunderEnum: string implements WithLabels, WithMatches, WithSelectableOptions
{
    use HasLabels;
    use HasMatches;
    use HasSelectableOptions;

    const DEFAULT = self::DEPED;

    case DEPED = 'depEd';
    case DSWD = 'dswd';
    case LGU = 'lgu';
    case LDS = 'lds';

    public static function labels(): array
    {
        return [
            self::DEPED->value => 'DepEd',
            self::DSWD->value => 'DSWD',
            self::LGU->value => 'LGU',
            self::LDS->value => 'LDS',
        ];
    }

    public static function beneficiaryLabels(): array
    {
        return [
            self::DEPED->value => 'DepEd No. of Beneficiaries',
            self::DSWD->value => 'DSWD No. of Beneficiaries',
            self::LGU->value => 'LGU No. of Beneficiaries',
            self::LDS->value => 'LDS No. of Beneficiaries',
        ];
    }

    public static function matches(): array
    {
        return [
            self::DEPED->value => ['DepEd', 'Department of Education', 'DEPED', 'deped', 'DepEd'],
            self::DSWD->value => ['DSWD', 'Department of Social Welfare and Development', 'dswd', 'DSWD'],
            self::LGU->value => ['LGU', 'Local Government Unit', 'lgu', 'LGU'],
            self::LDS->value => ['LDS', 'LDS', 'lds', 'LDS'],
        ];
    }

    public static function beneficiaryName(): array
    {
        return [
            self::DEPED->value => 'Department of Education (DepEd)',
            self::DSWD->value => 'Department of Social Welfare and Development (DSWD)',
            self::LGU->value => 'Local Government Unit (LGU)',
            self::LDS->value => 'Latter-Day Saints (LDS)',
        ];
    }
}
