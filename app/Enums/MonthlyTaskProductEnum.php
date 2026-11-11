<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum MonthlyTaskProductEnum: string implements WithLabels
{
    use HasLabels;

    case MILK = 'milk';
    case MEAT = 'meat';
    case MANURE = 'manure';

    public static function labels(): array
    {
        return [
            self::MILK->value => 'Milk',
            self::MEAT->value => 'Meat',
            self::MANURE->value => 'Manure',
        ];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function taskTable(string $taskType): ?string
    {
        return match ($this) {
            self::MILK => $taskType === MonthlyTaskTypeEnum::SALES->value ? 'milk_sales' : 'milk_productions',
            self::MEAT => $taskType === MonthlyTaskTypeEnum::SALES->value ? 'meat_sales' : 'meat_productions',
            self::MANURE => $taskType === MonthlyTaskTypeEnum::SALES->value ? 'manure_sales' : 'manure_productions',
        };
    }

    public function measureColumn(string $taskType): string
    {
        return match ($this) {
            self::MILK => $taskType === MonthlyTaskTypeEnum::SALES->value ? 'total_volume_sold' : 'total_volume_produced',
            self::MEAT => $taskType === MonthlyTaskTypeEnum::SALES->value ? 'total_sales' : 'total_volume',
            self::MANURE => $taskType === MonthlyTaskTypeEnum::SALES->value ? 'total_sales' : 'total_volume',
        };
    }
}
