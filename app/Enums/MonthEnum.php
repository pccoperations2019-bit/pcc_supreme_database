<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasSelectableOptions;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithSelectableOptions;

enum MonthEnum: int implements WithLabels, WithSelectableOptions
{
    use HasLabels;
    use HasSelectableOptions;

    case JANUARY = 1;
    case FEBRUARY = 2;
    case MARCH = 3;
    case APRIL = 4;
    case MAY = 5;
    case JUNE = 6;
    case JULY = 7;
    case AUGUST = 8;
    case SEPTEMBER = 9;
    case OCTOBER = 10;
    case NOVEMBER = 11;
    case DECEMBER = 12;

    public function abbreviation(): string
    {
        return strtolower(substr($this->label(), 0, 3));
    }

    public static function labels(): array
    {
        return [
            self::JANUARY->value => 'January',
            self::FEBRUARY->value => 'February',
            self::MARCH->value => 'March',
            self::APRIL->value => 'April',
            self::MAY->value => 'May',
            self::JUNE->value => 'June',
            self::JULY->value => 'July',
            self::AUGUST->value => 'August',
            self::SEPTEMBER->value => 'September',
            self::OCTOBER->value => 'October',
            self::NOVEMBER->value => 'November',
            self::DECEMBER->value => 'December',
        ];
    }
}
