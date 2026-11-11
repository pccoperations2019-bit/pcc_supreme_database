<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;

enum ManagementSystemEnum: string implements WithLabels, WithMatches
{
    use HasLabels;
    use HasMatches;

    const DEFAULT = self::PURE_CONFINEMENT;

    case PURE_CONFINEMENT = 'pure_confinement';
    case PARTIAL_CONFINEMENT = 'partial_confinement';
    case PURE_GRAZING = 'pure_grazing';
    case FULL_GRAZING = 'full_grazing';

    public static function labels(): array
    {
        return [
            self::PURE_CONFINEMENT->value => 'Pure Confinement',
            self::PARTIAL_CONFINEMENT->value => 'Partial Confinement',
            self::PURE_GRAZING->value => 'Pure Grazing',
            self::FULL_GRAZING->value => 'Full Grazing',
        ];
    }

    public static function matches(): array
    {
        return [
            self::PURE_CONFINEMENT->value => ['Pure Confinement', 'pure confinement', 'pure_confinement', 'confinement', 'full confinement'],
            self::PARTIAL_CONFINEMENT->value => ['Partial Confinement', 'partial confinement', 'partial_confinement', 'semi confinement', 'semi-confinement'],
            self::PURE_GRAZING->value => ['Pure Grazing', 'pure grazing', 'pure_grazing', 'grazing'],
            self::FULL_GRAZING->value => ['Full Grazing', 'full grazing', 'full_grazing'],
        ];
    }
}
