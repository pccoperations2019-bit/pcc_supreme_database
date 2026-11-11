<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;

/**
 * Who owns the land a dairy box site sits on.
 *
 * The dairy box form used to validate this field against `PartnerCategoryEnum`,
 * which is a list of *partner types*, and to reject anything else outright. Any
 * value an officer actually typed - "Other", "PCC" - came back as a validation
 * error, which left those dairy boxes impossible to edit at all.
 *
 * This list is the set of suggestions, not the set of permitted values: the form
 * adds an "Other" choice that reveals a free-text box, and the model casts the
 * column with `BackedEnumStringCast` so a value that is not a case - including
 * every value already in the database - reads back as the string it was saved as
 * rather than failing. Same approach as `MilkFeedingProgram::$casts`.
 */
enum LandOwnershipEnum: string implements WithLabels, WithMatches
{
    use HasLabels;

    // Matches the loose spellings a spreadsheet import can carry ("Local Government
    // Unit", "lgu", "LGU") without the import having to know the value list.
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

    /**
     * The spellings a spreadsheet or a typed form can carry for each case.
     *
     * The import resolves a cell against these before falling back to the raw text,
     * so "Local Government Unit", "LGU" and "lgu" all land on the same stored value
     * and show up as that one choice in the dropdown.
     *
     * @return array<string, array<int, string|int>>
     */
    public static function matches(): array
    {
        return [
            self::COOPERATIVE->value => ['coop', 'cooperative', 'co-op', 'Cooperative'],
            self::ASSOCIATION->value => ['assoc', 'association', 'Association'],
            self::LGU->value => ['lgu', 'local government', 'local government unit', 'LGU', 'Local Government Unit'],
            self::PGU->value => ['pgu', 'provincial government', 'provincial government unit', 'PGU', 'Provincial Government Unit'],
            self::PRIVATE->value => ['private', 'private sector', 'Private'],
            self::SUC->value => ['suc', 'state university', 'state college', 'SUC'],
            self::NGO->value => ['ngo', 'non-government', 'non-governmental', 'NGO', 'Non-Governmental Organization'],
            self::FAMILY_MODULE->value => ['family', 'family module', 'fm', 'Family Module'],
            self::CLUSTER->value => ['cluster', 'group', 'Cluster'],
            self::MULTIPLIER_FARM->value => ['multiplier', 'multiplier farm', 'mf', 'Multiplier Farm'],
            self::CORPORATION->value => ['corp', 'corporation', 'incorporated', 'Corporation'],
        ];
    }

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
}
