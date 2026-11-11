<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Concerns\HasSelectableOptions;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;
use App\Enums\Contracts\WithSelectableOptions;

enum AddressDistrictEnum: string implements WithLabels, WithMatches, WithSelectableOptions
{
    use HasLabels;
    use HasMatches;
    use HasSelectableOptions;

    case FIRST = 'I';
    case SECOND = 'II';
    case THIRD = 'III';
    case FOURTH = 'IV';
    case FIFTH = 'V';
    case SIXTH = 'VI';
    case SEVENTH = 'VII';
    case EIGHTH = 'VIII';
    case NINTH = 'IX';
    case TENTH = 'X';
    case ELEVENTH = 'XI';
    case TWELFTH = 'XII';
    case LONE = 'LONE';

    public static function labels(): array
    {
        return [
            self::FIRST->value => 'District 1',
            self::SECOND->value => 'District 2',
            self::THIRD->value => 'District 3',
            self::FOURTH->value => 'District 4',
            self::FIFTH->value => 'District 5',
            self::SIXTH->value => 'District 6',
            self::SEVENTH->value => 'District 7',
            self::EIGHTH->value => 'District 8',
            self::NINTH->value => 'District 9',
            self::TENTH->value => 'District 10',
            self::ELEVENTH->value => 'District 11',
            self::TWELFTH->value => 'District 12',
            self::LONE->value => 'Lone District',
        ];
    }

    public static function matches(): array
    {
        return [
            self::FIRST->value => ['District 1', '1st District', 'First District', 'I', 1, '1st', 'District I', 'DISTRICT 1', 'DISTRICT I', 'DIST 1', 'DIST I', 'D1', 'District No. 1'],
            self::SECOND->value => ['District 2', '2nd District', 'Second District', 'II', 2, '2nd', 'District II', 'DISTRICT 2', 'DISTRICT II', 'DIST 2', 'DIST II', 'D2', 'District No. 2'],
            self::THIRD->value => ['District 3', '3rd District', 'Third District', 'III', 3, '3rd', 'District III', 'DISTRICT 3', 'DISTRICT III', 'DIST 3', 'DIST III', 'D3', 'District No. 3'],
            self::FOURTH->value => ['District 4', '4th District', 'Fourth District', 'IV', 4, '4th', 'District IV', 'DISTRICT 4', 'DISTRICT IV', 'DIST 4', 'DIST IV', 'D4', 'District No. 4'],
            self::FIFTH->value => ['District 5', '5th District', 'Fifth District', 'V', 5, '5th', 'District V', 'DISTRICT 5', 'DISTRICT V', 'DIST 5', 'DIST V', 'D5', 'District No. 5'],
            self::SIXTH->value => ['District 6', '6th District', 'Sixth District', 'VI', 6, '6th', 'District VI', 'DISTRICT 6', 'DISTRICT VI', 'DIST 6', 'DIST VI', 'D6', 'District No. 6'],
            self::SEVENTH->value => ['District 7', '7th District', 'Seventh District', 'VII', 7, '7th', 'District VII', 'DISTRICT 7', 'DISTRICT VII', 'DIST 7', 'DIST VII', 'D7', 'District No. 7'],
            self::EIGHTH->value => ['District 8', '8th District', 'Eighth District', 'VIII', 8, '8th', 'District VIII', 'DISTRICT 8', 'DISTRICT VIII', 'DIST 8', 'DIST VIII', 'D8', 'District No. 8'],
            self::NINTH->value => ['District 9', '9th District', 'Ninth District', 'IX', 9, '9th', 'District IX', 'DISTRICT 9', 'DISTRICT IX', 'DIST 9', 'DIST IX', 'D9', 'District No. 9'],
            self::TENTH->value => ['District 10', '10th District', 'Tenth District', 'X', 10, '10th', 'District X', 'DISTRICT 10', 'DISTRICT X', 'DIST 10', 'DIST X', 'D10', 'District No. 10'],
            self::ELEVENTH->value => ['District 11', '11th District', 'Eleventh District', 'XI', 11, '11th', 'District XI', 'DISTRICT 11', 'DISTRICT XI', 'DIST 11', 'DIST XI', 'D11', 'District No. 11'],
            self::TWELFTH->value => ['District 12', '12th District', 'Twelfth District', 'XII', 12, '12th', 'District XII', 'DISTRICT 12', 'DISTRICT XII', 'DIST 12', 'DIST XII', 'D12', 'District No. 12'],
            self::LONE->value => ['Lone District', 'Lone Dist.', 'Lone Dist', 'LONE', 0, 'Lone', 'District Lone', 'DISTRICT LONE', 'DIST LONE', 'Only District', 'Single District'],
        ];
    }

    /**
     * Canonical short (ordinal) names for each district. Used to normalize the
     * messy values stored in `addresses.district` (roman numerals, numbers,
     * "District N", "Nth District", ...) into a single readable form, e.g.
     * "I" / "1" / "District 1" / "1st District" all -> "1st".
     *
     * @return array<string, string>
     */
    public static function ordinals(): array
    {
        return [
            self::FIRST->value => '1st',
            self::SECOND->value => '2nd',
            self::THIRD->value => '3rd',
            self::FOURTH->value => '4th',
            self::FIFTH->value => '5th',
            self::SIXTH->value => '6th',
            self::SEVENTH->value => '7th',
            self::EIGHTH->value => '8th',
            self::NINTH->value => '9th',
            self::TENTH->value => '10th',
            self::ELEVENTH->value => '11th',
            self::TWELFTH->value => '12th',
            self::LONE->value => 'Lone District',
        ];
    }
}
