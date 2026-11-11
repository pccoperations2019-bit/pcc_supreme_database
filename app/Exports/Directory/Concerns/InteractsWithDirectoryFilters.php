<?php

namespace App\Exports\Directory\Concerns;

use App\Enums\DocumentEnum;
use App\Enums\PartnerCategoryEnum;
use App\Enums\ProfileStatusEnum;
use App\Enums\ProjectProgramEnum;
use App\Support\Centers;
use Illuminate\Support\Facades\DB;

trait InteractsWithDirectoryFilters
{
    private function applyWhereIn($query, string $column, int|array|null|string $value): void
    {
        if ($value === 'all') {
            return;
        }

        if (is_array($value)) {
            $query->whereIn($column, $value);
        } else {
            $query->where($column, $value);
        }
    }

    private function applyStatusFilter($query, string $table, string|array|null $status): void
    {
        $dbValues = collect((array) $status)
            ->map(fn ($status) => ProfileStatusEnum::fromLabel($status)?->value ?? $status)
            ->all();

        $query->whereIn("{$table}.status", $dbValues);
    }

    private function applyProjectFilter($query, string $table, string|array|null $project): void
    {
        $dbValues = collect((array) $project)
            ->map(fn ($project) => ProjectProgramEnum::fromLabel($project)?->value ?? $project)
            ->all();

        $projectIds = DB::table('projects')->whereIn('program', $dbValues)->pluck('id');

        if ($table === 'partners') {
            $query->where(function ($nested) use ($table, $projectIds) {
                $nested->whereIn("{$table}.project_id", $projectIds);
                $nested->orWhereExists(function ($subQuery) use ($table, $projectIds) {
                    $subQuery->from('partner_project')
                        ->whereColumn('partner_project.partner_id', "{$table}.id")
                        ->whereIn('partner_project.project_id', $projectIds);
                });
            });
        } elseif ($table === 'farmers') {
            $query->where(function ($nested) use ($table, $projectIds, $dbValues) {
                $nested->whereExists(function ($subQuery) use ($table, $projectIds) {
                    $subQuery->from('farmer_project')
                        ->whereColumn('farmer_project.farmer_id', "{$table}.id")
                        ->whereIn('farmer_project.project_id', $projectIds);
                });
                // Also match the farmer profile project category (comma-separated),
                // mirroring how partners match both project_id and the pivot table.
                foreach ($dbValues as $value) {
                    $nested->orWhereRaw("FIND_IN_SET(?, {$table}.project_category)", [$value]);
                }
            });
        } else {
            $query->whereIn("{$table}.project_id", $projectIds);
        }
    }

    private function applyPartnerTypeFilter($query, string|array|null $partnerType): void
    {
        $dbValues = collect((array) $partnerType)
            ->map(fn ($type) => PartnerCategoryEnum::fromLabel($type)?->value ?? $type)
            ->all();

        $query->where(function ($nested) use ($dbValues) {
            $nested->whereIn('partners.category', $dbValues);
            foreach ($dbValues as $value) {
                $nested->orWhereRaw('JSON_UNQUOTE(JSON_EXTRACT(partners.metadata, "$.partner_businesstype")) = ?', [$value]);
                $nested->orWhereRaw('JSON_UNQUOTE(JSON_EXTRACT(partners.metadata, "$.partner_category")) = ?', [$value]);
            }
        });
    }

    /** @deprecated use applyPartnerTypeFilter instead — kept for BC */
    private function applyBusinessTypeFilter($query, string|array|null $businessType): void
    {
        $this->applyPartnerTypeFilter($query, $businessType);
    }

    private function applyHiddenCentersFilter($query): void
    {
        // Always-hidden set (NHQ) comes from Support\Centers so this export and
        // the dropdowns cannot drift apart; the GP/NIZ halves stay program-scoped
        // below, which Support\Centers::hiddenCenters() cannot express here.
        $hiddenCodes = array_map('strtoupper', Centers::alwaysHiddenCenters());

        $query->where(function ($nested) use ($hiddenCodes) {
            $nested->whereNotIn('centers.code', $hiddenCodes)
                ->where(function ($subQuery) {
                    $subQuery->where('projects.program', '!=', 'cbed')
                        ->orWhere('centers.code', '!=', 'GP');
                })
                ->where(function ($subQuery) {
                    $subQuery->where('projects.program', '!=', 'ih')
                        ->orWhere('centers.code', '!=', 'NIZ');
                });
        });
    }

    private function accreditationColumn(string $value): ?string
    {
        $norm = strtolower(trim($value));
        // Legacy has_* mapping for backwards compat
        $map = [
            'fda_lto' => 'has_fda_lto',
            'fda-lto' => 'has_fda_lto',
            'nda_lto' => 'has_nda_lto',
            'nda-lto' => 'has_nda_lto',
            'philgeps' => 'has_philgeps',
            'mphilgeps' => 'has_philgeps',
            'm_philgeps' => 'has_philgeps',
            'ffedis' => 'has_ffedis',
            'rsbsa' => 'has_rsbsa',
            'cso' => 'has_cso',
        ];
        if (isset($map[$norm])) {
            return $map[$norm];
        }
        // Generic: any DocumentEnum value (e.g., bir, iso, cda, sec, dole, etc.)
        $enumNorm = str_replace('_', '-', $norm);
        if (DocumentEnum::tryFrom($enumNorm) !== null) {
            return 'has_'.str_replace('-', '_', $enumNorm);
        }
        // Try label match (e.g., "FDA-LTO" -> "fda-lto")
        foreach (DocumentEnum::labels() as $enumValue => $label) {
            if (strtolower($label) === $norm || strtolower($enumValue) === $norm || strtolower(str_replace([' ', '_'], '-', $label)) === $enumNorm) {
                return 'has_'.str_replace('-', '_', $enumValue);
            }
        }

        return null;
    }

    /**
     * Resolve accreditation filter value to DocumentEnum value (e.g., "fda_lto" -> "fda-lto").
     * Supports all DocumentEnum cases, not just the 6 has_* ones.
     */
    protected function documentTypeFromAccreditation(string $value): ?string
    {
        $norm = strtolower(trim($value));
        $enumNorm = str_replace('_', '-', $norm);
        // Direct enum match
        if (DocumentEnum::tryFrom($enumNorm) !== null) {
            return $enumNorm;
        }
        // Legacy has_* mapping
        $hasCol = $this->accreditationColumn($value);
        if ($hasCol !== null) {
            return match ($hasCol) {
                'has_cso' => DocumentEnum::CSO->value,
                'has_fda_lto' => DocumentEnum::FDA_LTO->value,
                'has_nda_lto' => DocumentEnum::NDA_LTO->value,
                'has_philgeps' => DocumentEnum::PHILGEPS->value,
                'has_ffedis' => DocumentEnum::FFEDIS->value,
                'has_rsbsa' => DocumentEnum::RSBSA->value,
                default => str_replace('has_', '', $hasCol) ? str_replace('_', '-', str_replace('has_', '', $hasCol)) : null,
            };
        }

        return null;
    }
}
