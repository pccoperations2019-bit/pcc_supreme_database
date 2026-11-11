<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasSelectableOptions;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithSelectableOptions;

enum ProgramEnum: string implements WithLabels, WithSelectableOptions
{
    use HasLabels;
    use HasSelectableOptions;

    public const DEFAULT = self::IH;

    case IH = 'ih';
    case CBED = 'cbed';

    public static function labels(): array
    {
        return [
            self::IH->value => 'IH',
            self::CBED->value => 'CBED',
        ];
    }

    public static function options(): array
    {
        return [
            [
                'label' => 'IH',
                'value' => self::IH->value,
                'image' => '/images/center.png',
            ],
            [
                'label' => 'CBED',
                'value' => self::CBED->value,
                'image' => '/images/carabao.png',
            ],
        ];
    }

    public function option(): array
    {
        return collect(self::options())->firstWhere('value', $this->value);
    }

    /**
     * The stored values, for validating a filter or a request payload.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function badges(): array
    {
        return [
            self::IH->value => 'badge-ih',
            self::CBED->value => 'badge-cbed',
        ];
    }
}
