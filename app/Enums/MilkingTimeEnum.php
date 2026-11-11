<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasSelectableOptions;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithSelectableOptions;

enum MilkingTimeEnum: string implements WithLabels, WithSelectableOptions
{
    use HasLabels;
    use HasSelectableOptions;

    public const DEFAULT = self::AM;

    case AM = 'am';
    case PM = 'pm';
    case AM_PM = 'am_pm';

    public static function labels(): array
    {
        return [
            self::AM->value => 'AM',
            self::PM->value => 'PM',
            self::AM_PM->value => 'AM & PM',
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::AM => 'AM',
            self::PM => 'PM',
            self::AM_PM => 'AM & PM',
        };
    }

    public static function match(int|string $key): self
    {
        $key = match ($key) {
            'am' => MilkingTimeEnum::AM->value,
            'pm' => MilkingTimeEnum::PM->value,
            'am & pm', 'both' => MilkingTimeEnum::AM_PM->value,
            default => MilkingTimeEnum::DEFAULT->value,
        };

        return self::tryFrom($key);
    }
}
