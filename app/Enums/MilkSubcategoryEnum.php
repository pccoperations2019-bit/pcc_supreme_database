<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Concerns\HasSelectableOptions;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;
use App\Enums\Contracts\WithSelectableOptions;

enum MilkSubcategoryEnum: string implements WithLabels, WithMatches, WithSelectableOptions
{
    use HasLabels;
    use HasMatches;
    use HasSelectableOptions;

    public const DEFAULT = self::PLAIN;

    case PLAIN = 'plain';
    case CHOCOMILK = 'chocomilk';
    case FLAVORED = 'flavored';

    public static function labels(): array
    {
        return [
            self::PLAIN->value => 'Plain',
            self::CHOCOMILK->value => 'Chocomilk',
            self::FLAVORED->value => 'Flavored',
        ];
    }

    public static function matches(): array
    {
        return [
            self::PLAIN->value => ['plain', 'regular', 'original'],
            self::CHOCOMILK->value => ['chocomilk', 'chocolate', 'choco milk', 'chocolate milk'],
            self::FLAVORED->value => ['flavored', 'strawberry', 'vanilla', 'banana', 'flavor'],
        ];
    }
}
