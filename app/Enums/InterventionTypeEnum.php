<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum InterventionTypeEnum: string implements WithLabels
{
    use HasLabels;

    const DEFAULT = self::TRAINING;

    case TRAINING = 'training';
    case CONSULTANCY = 'consultancy';
    case FINANCIAL_ASSISTANCE = 'financial_assistance';
    case EQUIPMENT_PROVISION = 'equipment_provision';
    case TECHNICAL_ASSISTANCE = 'technical_assistance';

    public static function labels(): array
    {
        return [
            self::TRAINING->value => 'Training',
            self::CONSULTANCY->value => 'Consultancy',
            self::FINANCIAL_ASSISTANCE->value => 'Financial Assistance',
            self::EQUIPMENT_PROVISION->value => 'Equipment Provision',
            self::TECHNICAL_ASSISTANCE->value => 'Technical Assistance',
        ];
    }
}
