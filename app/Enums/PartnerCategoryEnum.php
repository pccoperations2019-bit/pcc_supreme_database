<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;

enum PartnerCategoryEnum: string implements WithLabels, WithMatches
{
    use HasLabels;
    use HasMatches;

    case COOPERATIVE = 'cooperative';
    case ASSOCIATION = 'association';
    case LGU = 'lgu';
    case SUC = 'suc';
    case PGU = 'pgu';
    case PRIVATE = 'private';
    case NGO = 'ngo';
    case FAMILY_MODULE = 'family_module';
    case CLUSTER = 'cluster';
    case MULTIPLIER_FARM = 'multiplier_farm';
    case CORPORATION = 'corporation';

    public static function labels(): array
    {
        return [
            self::COOPERATIVE->value => 'Cooperative',
            self::ASSOCIATION->value => 'Association',
            self::LGU->value => 'Local Government Unit',
            self::SUC->value => 'SUC',
            self::PGU->value => 'Provincial Government Unit',
            self::PRIVATE->value => 'Private',
            self::NGO->value => 'NGO',
            self::FAMILY_MODULE->value => 'Family Module',
            self::CLUSTER->value => 'Cluster',
            self::MULTIPLIER_FARM->value => 'Multiplier Farm',
            self::CORPORATION->value => 'Corporation',
        ];
    }

    public static function matches(): array
    {
        return [
            self::COOPERATIVE->value => ['coop', 'cooperative', 'co-op', 'Cooperative'],
            self::ASSOCIATION->value => ['assoc', 'association', 'Association'],
            self::LGU->value => ['lgu', 'local government', 'local government unit', 'LGU', 'Local Government Unit'],
            self::SUC->value => ['suc', 'state university', 'state college', 'SUC'],
            self::PGU->value => ['pgu', 'provincial government', 'provincial government unit', 'PGU', 'Provincial Government Unit'],
            self::PRIVATE->value => ['private', 'private sector', 'Private'],
            self::NGO->value => ['ngo', 'non-government', 'non-governmental', 'NGO', 'Non-Governmental Organization'],
            self::FAMILY_MODULE->value => ['family', 'family module', 'fm', 'Family Module'],
            self::CLUSTER->value => ['cluster', 'group', 'Cluster'],
            self::MULTIPLIER_FARM->value => ['multiplier', 'multiplier farm', 'mf', 'Multiplier Farm'],
            self::CORPORATION->value => ['corp', 'corporation', 'incorporated', 'Corporation'],
        ];
    }

    public static function normalize(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $candidate = trim((string) $value);

        foreach (self::cases() as $case) {
            if (strcasecmp($case->value, $candidate) === 0) {
                return $case->value;
            }
        }

        foreach (self::labels() as $caseValue => $label) {
            if (strcasecmp($label, $candidate) === 0) {
                return $caseValue;
            }
        }

        return null;
    }
}
