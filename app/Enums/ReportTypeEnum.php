<?php

namespace App\Enums;

enum ReportTypeEnum: string
{
    case PRODUCTION = 'production';
    case SALES = 'sales';

    public static function categories(): array
    {
        return [
            self::PRODUCTION->value,
            self::SALES->value,
        ];
    }

    public function labels(): string
    {
        return match ($this) {
            self::PRODUCTION => 'Production',
            self::SALES => 'Sales',
        };
    }

    public function colors(): array
    {
        return array_map(fn (self $case) => $case->color(), self::cases());
    }

    public function color(): string
    {
        return match ($this) {
            self::PRODUCTION => '#4F81BD',
            self::SALES => '#2E75B6',
        };
    }

    public static function reportColors(): array
    {
        return [
            'report' => '#4F81BD',
            'sum' => '#9BBB59',
            'target' => '#C0504D',
            'accomplishment' => [
                'high' => '#22c55e',
                'mid' => '#facc15',
                'low' => '#ef4444',
            ],
        ];
    }
}
