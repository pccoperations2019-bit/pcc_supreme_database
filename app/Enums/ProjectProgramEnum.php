<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Concerns\HasSelectableOptions;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;
use App\Enums\Contracts\WithSelectableOptions;

enum ProjectProgramEnum: string implements WithLabels, WithMatches, WithSelectableOptions
{
    use HasLabels, HasMatches, HasSelectableOptions;

    const DEFAULT = self::CBED;

    case MFP = 'mfp';
    case PL480 = 'pl480';
    case DHB = 'dhb';
    case CBIN = 'cbin';
    case CBED = 'cbed';
    case CCDP = 'ccdp';
    case CHBU = 'chbu';
    case CARABAO = 'carabao';

    public static function labels(): array
    {
        return [
            self::MFP->value => 'MFP',
            self::PL480->value => 'PL480',
            self::DHB->value => 'DHB',
            self::CBIN->value => 'CBIN',
            self::CBED->value => 'CBED',
            self::CCDP->value => 'CCDP',
            self::CHBU->value => 'CHBU',
            self::CARABAO->value => 'Carabao',
        ];
    }

    public static function matches(): array
    {
        return [
            self::CBED->value => ['CBED', 'cbed'],
            self::PL480->value => ['PL480', 'pl480', 'pl-480', 'pl 480'],
            self::DHB->value => ['DHB', 'dhb'],
            self::CBIN->value => ['CBIN', 'cbin'],
            self::CCDP->value => ['CCDP', 'ccdp', 'CDP'],
            self::CHBU->value => ['CHBU', 'chbu'],
            self::CARABAO->value => ['Carabao', 'carabao'],
            self::MFP->value => ['MFP', 'mfp', 'RA10037', 'ra10037', 'RA 10037', 'ra 10037'],
        ];
    }

    public static function fromLegacyValue(string $value): self
    {
        return match (strtolower($value)) {
            'ra10037' => self::MFP,
            default => self::from($value),
        };
    }
}
