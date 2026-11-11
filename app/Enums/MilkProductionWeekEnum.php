<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;

enum MilkProductionWeekEnum: int implements WithLabels, WithMatches
{
    use HasLabels;

    case WEEK_1 = 1;
    case WEEK_2 = 2;
    case WEEK_3 = 3;
    case WEEK_4 = 4;
    case WEEK_5 = 5;
    case WEEK_6 = 6;
    case WEEK_7 = 7;
    case WEEK_8 = 8;
    case WEEK_9 = 9;
    case WEEK_10 = 10;
    case WEEK_11 = 11;
    case WEEK_12 = 12;
    case WEEK_13 = 13;
    case WEEK_14 = 14;
    case WEEK_15 = 15;
    case WEEK_16 = 16;
    case WEEK_17 = 17;
    case WEEK_18 = 18;
    case WEEK_19 = 19;
    case WEEK_20 = 20;
    case WEEK_21 = 21;
    case WEEK_22 = 22;
    case WEEK_23 = 23;
    case WEEK_24 = 24;
    case WEEK_25 = 25;
    case WEEK_26 = 26;
    case WEEK_27 = 27;
    case WEEK_28 = 28;
    case WEEK_29 = 29;
    case WEEK_30 = 30;
    case WEEK_31 = 31;
    case WEEK_32 = 32;
    case WEEK_33 = 33;
    case WEEK_34 = 34;
    case WEEK_35 = 35;
    case WEEK_36 = 36;
    case WEEK_37 = 37;
    case WEEK_38 = 38;
    case WEEK_39 = 39;
    case WEEK_40 = 40;
    case WEEK_41 = 41;
    case WEEK_42 = 42;
    case WEEK_43 = 43;
    case WEEK_44 = 44;
    case WEEK_45 = 45;
    case WEEK_46 = 46;
    case WEEK_47 = 47;
    case WEEK_48 = 48;
    case WEEK_49 = 49;
    case WEEK_50 = 50;
    case WEEK_51 = 51;
    case WEEK_52 = 52;
    case WEEK_53 = 53;

    public static function labels(): array
    {
        $labels = [];
        for ($i = 1; $i <= 53; $i++) {
            $labels[$i] = "Week {$i}";
        }

        return $labels;
    }

    public static function matches(): array
    {
        $matches = [];
        for ($i = 1; $i <= 53; $i++) {
            $matches[$i] = [
                "week {$i}",
                "wk {$i}",
                "w{$i}",
                (string) $i,
            ];
        }

        return $matches;
    }

    public static function tryFroMatches(?string $label): ?self
    {
        if (! $label) {
            return null;
        }

        $label = trim(strtolower($label));

        if ($label === '') {
            return null;
        }

        if (preg_match('/^\d{1,2}$/', $label)) {
            return self::fromInt((int) $label);
        }

        if (preg_match('/\b(?:week|wk|w)\s*(\d{1,2})\b/i', $label, $m)) {
            return self::fromInt((int) $m[1]);
        }

        return null;
    }

    /**
     * Get week enum from integer value
     */
    public static function fromInt(int|string|null $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        $weekNumber = (int) $value;

        if ($weekNumber < 1 || $weekNumber > 53) {
            return null;
        }

        return self::tryFrom($weekNumber);
    }

    /**
     * Validate if a week number is valid (1-53)
     */
    public static function isValid(int|string|null $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $weekNumber = (int) $value;

        return $weekNumber >= 1 && $weekNumber <= 53;
    }

    /**
     * Get week number from a date, ensuring it stays within the calendar year
     * Handles year boundaries where ISO week might assign week 1 to December dates
     */
    public static function fromDate(\DateTimeInterface $date): ?self
    {
        $weekNumber = (int) $date->format('W');
        $isoWeekYear = (int) $date->format('o');
        $calendarYear = (int) $date->format('Y');
        $month = (int) $date->format('m');

        if ($isoWeekYear !== $calendarYear) {
            return self::fromInt($month === 12 ? 53 : 1);
        }

        return self::fromInt($weekNumber);
    }
}
