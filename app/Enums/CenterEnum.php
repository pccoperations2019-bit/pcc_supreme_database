<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Concerns\HasSelectableOptions;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithSelectableOptions;
use App\Models\Center;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

enum CenterEnum: string implements WithLabels, WithSelectableOptions
{
    use HasLabels;
    use HasMatches;
    use HasSelectableOptions;

    case MMSU = 'mmsu';
    case DMMMSU = 'dmmmsu';
    case CSU = 'csu';
    case CLSU = 'clsu';
    case UPLB = 'uplb';
    case WVSU = 'wvsu';
    case LCSF = 'lcsf';
    case USF = 'usf';
    case VSU = 'vsu';
    case MLPC = 'mlpc';
    case CMU = 'cmu';
    case USM = 'usm';
    case MSU = 'msu';
    case GP = 'gp';
    case NIZ = 'niz';
    case NHQ = 'nhq';

    public static function labels(): array
    {
        return array_map(fn (self $case) => match ($case) {
            self::MMSU => 'MMSU',
            self::DMMMSU => 'DMMMSU',
            self::CSU => 'CSU',
            self::CLSU => 'CLSU',
            self::UPLB => 'UPLB',
            self::WVSU => 'WVSU',
            self::LCSF => 'LCSF',
            self::USF => 'USF',
            self::VSU => 'VSU',
            self::MLPC => 'MLPC',
            self::CMU => 'CMU',
            self::USM => 'USM',
            self::MSU => 'MSU',
            self::GP => 'GP',
            self::NIZ => 'NIZ',
            self::NHQ => 'NHQ',
        }, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::MMSU => 'PCC at MMSU',
            self::DMMMSU => 'PCC at DMMMSU',
            self::CSU => 'PCC at CSU',
            self::CLSU => 'PCC at CLSU',
            self::UPLB => 'PCC at UPLB',
            self::WVSU => 'PCC at WVSU',
            self::LCSF => 'PCC at LCSF',
            self::USF => 'PCC at USF',
            self::VSU => 'PCC at VSU',
            self::MLPC => 'PCC at MLPC',
            self::CMU => 'PCC at CMU',
            self::USM => 'PCC at USM',
            self::MSU => 'PCC at MSU',
            self::GP => 'PCC at GP',
            self::NIZ => 'PCC at NIZ',
            self::NHQ => 'PCC at NHQ',
        };
    }

    public function fullName(): string
    {
        return match ($this) {
            self::MMSU => 'PCC at Mariano Marcos State University',
            self::DMMMSU => 'PCC at Don Mariano Marcos Memorial State University',
            self::CSU => 'PCC at Cagayan State University',
            self::CLSU => 'PCC at Central Luzon State University',
            self::UPLB => 'PCC at University of the Philippines Los Baños',
            self::WVSU => 'PCC at West Visayas State University',
            self::LCSF => 'PCC at La Carlota Stock Farm',
            self::USF => 'PCC at Ubay Stock Farm',
            self::VSU => 'PCC at Visayas State University',
            self::MLPC => 'PCC at Mindanao Livestock Production Center',
            self::CMU => 'PCC at Central Mindanao University',
            self::USM => 'PCC at University of Southern Mindanao',
            self::MSU => 'PCC at Mindanao State University',
            self::GP => 'PCC at Gene Pool',
            self::NIZ => 'PCC at NIZ',
            self::NHQ => 'PCC at National Headquarters',
        };
    }

    public function pccFullLabel(): string
    {
        return $this->fullName();
    }

    public static function labelsByCode(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $center) => [$center->code() => $center->label()])
            ->toArray();
    }

    public static function fullLabelsByCode(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $center) => [$center->code() => $center->fullName()])
            ->toArray();
    }

    public static function getCenterOptions(): Collection
    {
        $centerIds = Center::pluck('id', 'code')->toArray();

        return collect([['id' => 'all', 'label' => 'All Centers']])
            ->merge(
                collect(self::cases())->map(fn (self $case) => [
                    'id' => $centerIds[strtoupper($case->value)] ?? null,
                    'label' => $case->label(),
                ])->filter(fn ($item) => $item['id'] !== null)
            );
    }

    public static function clusterLabels(): array
    {
        $centerIds = Center::pluck('id', 'code')->toArray();

        return [
            'National' => [
                [
                    'label' => self::NHQ->label(),
                    'value' => self::NHQ->code(),
                    'id' => $centerIds[self::NHQ->code()] ?? null,
                ],
                [
                    'label' => self::GP->label(),
                    'value' => self::GP->code(),
                    'id' => $centerIds[self::GP->code()] ?? null,
                ],
                [
                    'label' => self::NIZ->label(),
                    'value' => self::NIZ->code(),
                    'id' => $centerIds[self::NIZ->code()] ?? null,
                ],
            ],
            'Luzon Cluster' => [
                [
                    'label' => self::CLSU->label(),
                    'value' => self::CLSU->code(),
                    'id' => $centerIds[self::CLSU->code()] ?? null,
                ],
                ['label' => self::CSU->label(), 'value' => self::CSU->code(), 'id' => $centerIds[self::CSU->code()] ?? null],
                ['label' => self::MMSU->label(), 'value' => self::MMSU->code(), 'id' => $centerIds[self::MMSU->code()] ?? null],
                ['label' => self::DMMMSU->label(), 'value' => self::DMMMSU->code(), 'id' => $centerIds[self::DMMMSU->code()] ?? null],
                ['label' => self::UPLB->label(), 'value' => self::UPLB->code(), 'id' => $centerIds[self::UPLB->code()] ?? null],
            ],
            'Visayas Cluster' => [
                ['label' => self::WVSU->label(), 'value' => self::WVSU->code(), 'id' => $centerIds[self::WVSU->code()] ?? null],
                ['label' => self::VSU->label(), 'value' => self::VSU->code(), 'id' => $centerIds[self::VSU->code()] ?? null],
                ['label' => self::USF->label(), 'value' => self::USF->code(), 'id' => $centerIds[self::USF->code()] ?? null],
                ['label' => self::LCSF->label(), 'value' => self::LCSF->code(), 'id' => $centerIds[self::LCSF->code()] ?? null],
            ],
            'Mindanao Cluster' => [
                ['label' => self::CMU->label(), 'value' => self::CMU->code(), 'id' => $centerIds[self::CMU->code()] ?? null],
                ['label' => self::MLPC->label(), 'value' => self::MLPC->code(), 'id' => $centerIds[self::MLPC->code()] ?? null],
                ['label' => self::USM->label(), 'value' => self::USM->code(), 'id' => $centerIds[self::USM->code()] ?? null],
            ],
        ];
    }

    public static function categories(): array
    {
        return array_map(fn (self $case) => $case->code(), self::cases());
    }

    public static function colors(): array
    {
        return array_map(fn (self $case) => $case->color(), self::cases());
    }

    public function color(): string
    {
        return match ($this) {
            self::MMSU => '#F4AAA9',
            self::DMMMSU => '#7452B6',
            self::CSU => '#F7ECA6',
            self::CLSU => '#9AE66A',
            self::UPLB => '#C1583F',
            self::WVSU => '#D5B15B',
            self::LCSF => '#8CB8E4',
            self::USF => '#F9A6A4',
            self::VSU => '#C96B36',
            self::MLPC => '#9F9E37',
            self::CMU => '#F3B33D',
            self::USM => '#E99163',
            self::MSU => '#76D7FA',
            self::GP => '#76D7FA',
            self::NIZ => '#76D7FA',
            self::NHQ => '#76D7FA',
        };
    }

    public function code(): string
    {
        return Str::upper($this->value);
    }

    public function sortOrder(): int
    {
        return match ($this) {
            self::CLSU => 1,
            self::CMU => 2,
            self::CSU => 3,
            self::DMMMSU => 4,
            self::GP => 5,
            self::LCSF => 6,
            self::MLPC => 7,
            self::MMSU => 8,
            self::MSU => 9,
            self::NHQ => 10,
            self::NIZ => 11,
            self::UPLB => 12,
            self::USF => 13,
            self::USM => 14,
            self::VSU => 15,
            self::WVSU => 16,
        };
    }

    public static function codeSortExpression(string $centerIdColumn = 'center_id'): string
    {
        return "(SELECT code FROM centers WHERE centers.id = {$centerIdColumn})";
    }

    public static function sortedCodes(): array
    {
        return collect(self::cases())
            ->sortBy(fn ($c) => $c->sortOrder())
            ->map(fn ($c) => $c->code())
            ->values()
            ->all();
    }

    public static function matches(): array
    {
        return [
            self::MMSU->value => ['mmsu', 'mariano marcos state university', 'mariano marcos'],
            self::DMMMSU->value => ['dmmmsu', 'don mariano marcos memorial state university', 'don mariano'],
            self::CSU->value => ['csu', 'cagayan state university', 'cagayan'],
            self::CLSU->value => ['clsu', 'central luzon state university', 'central luzon'],
            self::UPLB->value => ['uplb', 'university of the philippines los baños', 'university of the philippines', 'up los banos'],
            self::WVSU->value => ['wvsu', 'west visayas state university', 'west visayas'],
            self::LCSF->value => ['lcsf', 'la carlota stock farm', 'la carlota'],
            self::USF->value => ['usf', 'ubay stock farm', 'ubay'],
            self::VSU->value => ['vsu', 'visayas state university', 'visayas'],
            self::MLPC->value => ['mlpc', 'mindanao livestock production center', 'mindanao livestock'],
            self::CMU->value => ['cmu', 'central mindanao university', 'central mindanao'],
            self::USM->value => ['usm', 'university of southern mindanao', 'southern mindanao'],
            self::MSU->value => ['msu', 'mindanao state university'],
            self::GP->value => ['gp', 'gene pool', 'pcc at gene pool', 'Gene Pool'],
            self::NIZ->value => ['niz'],
            self::NHQ->value => ['nhq', 'national headquarters', 'headquarters', 'pccnhq'],
        ];
    }
}
