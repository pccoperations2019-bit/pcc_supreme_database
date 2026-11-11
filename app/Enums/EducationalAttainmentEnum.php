<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;

enum EducationalAttainmentEnum: string implements WithLabels, WithMatches
{
    use HasLabels;
    use HasMatches;

    const DEFAULT = self::COLLEGE_GRADUATE;

    case NO_FORMAL_EDUCATION = 'no_formal_education';
    case ELEMENTARY_LEVEL = 'elementary_level';
    case ELEMENTARY_GRADUATE = 'elementary_graduate';
    case HIGH_SCHOOL_LEVEL = 'high_school_level';
    case HIGH_SCHOOL_GRADUATE = 'high_school_graduate';
    case VOCATIONAL_LEVEL = 'vocational_level';
    case VOCATIONAL_GRADUATE = 'vocational_graduate';
    case COLLEGE_LEVEL = 'college_level';
    case COLLEGE_GRADUATE = 'college_graduate';
    case POST_GRADUATE = 'post_graduate';

    public static function labels(): array
    {
        return [
            self::NO_FORMAL_EDUCATION->value => 'No Formal Education',
            self::ELEMENTARY_LEVEL->value => 'Elementary Level',
            self::ELEMENTARY_GRADUATE->value => 'Elementary Graduate',
            self::HIGH_SCHOOL_LEVEL->value => 'High School Level',
            self::HIGH_SCHOOL_GRADUATE->value => 'High School Graduate',
            self::VOCATIONAL_LEVEL->value => 'Vocational Level',
            self::VOCATIONAL_GRADUATE->value => 'Vocational Graduate',
            self::COLLEGE_LEVEL->value => 'College Level',
            self::COLLEGE_GRADUATE->value => 'College Graduate',
            self::POST_GRADUATE->value => 'Post Graduate',
        ];
    }

    public static function matches(): array
    {
        return [
            self::NO_FORMAL_EDUCATION->value => ['No Formal Education', 'no formal education', 'none', 'n/a', 'na'],
            self::ELEMENTARY_LEVEL->value => ['Elementary Level', 'elementary level', 'elementary'],
            self::ELEMENTARY_GRADUATE->value => ['Elementary Graduate', 'elementary graduate', 'elem graduate', 'grade school graduate', 'primary graduate'],
            self::HIGH_SCHOOL_LEVEL->value => ['High School Level', 'high school level', 'hs level', 'secondary level'],
            self::HIGH_SCHOOL_GRADUATE->value => ['High School Graduate', 'high school graduate', 'hs graduate', 'secondary graduate'],
            self::VOCATIONAL_LEVEL->value => ['Vocational Level', 'vocational level', 'techvoc level', 'tesda level'],
            self::VOCATIONAL_GRADUATE->value => ['Vocational Graduate', 'vocational graduate', 'techvoc graduate', 'tesda graduate'],
            self::COLLEGE_LEVEL->value => ['College Level', 'college level', 'college undergraduate', 'undergraduate'],
            self::COLLEGE_GRADUATE->value => ['College Graduate', 'college graduate'],
            self::POST_GRADUATE->value => ['Post Graduate', 'post graduate', 'postgraduate', 'masters', 'masteral', 'phd', 'doctorate'],
        ];
    }
}
