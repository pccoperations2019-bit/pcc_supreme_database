<?php

namespace App\Queries\Reports;

use App\Enums\AddressRegionEnum;
use App\Enums\DocumentEnum;
use App\Enums\MilkFeedingProgramFunderEnum;
use App\Enums\MilkTypeEnum;
use App\Enums\ModeOfProcurementEnum;
use App\Enums\MonthEnum;
use App\Enums\PartnerCategoryEnum;
use App\Enums\ProfileStatusEnum;
use App\Enums\ProgramEnum;
use App\Enums\ProjectProgramEnum;
use App\Models\User;
use App\Support\Centers;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateReportQuery
{
    public function execute(array $queryFilters = [], ?User $user = null): array
    {
        $reportType = $queryFilters['report_type'] ?? 'bdp';
        $centerOptions = $this->buildCenterOptions($user);

        return [
            'report_type' => $reportType,
            'report_filters' => $this->buildReportFilters($reportType, $centerOptions),
            'redirect_filters' => $this->buildRedirectFilters($queryFilters, $user),
        ];
    }

    private function buildCenterOptions(?User $user): Collection
    {
        $centerOptions = Centers::getCenterOptions();

        if ($user?->isCenterScoped() && $user->center_id !== null) {
            return $centerOptions
                ->filter(fn ($option) => $option['id'] === $user->center_id)
                ->values();
        }

        return $centerOptions;
    }

    private function buildYearOptions(): Collection
    {
        return Cache::remember('bdp_year_options', 3600, function () {
            return collect([['id' => 'all', 'label' => 'All Years']])
                ->merge(
                    collect(['milk_productions', 'meat_productions', 'manure_productions'])
                        ->flatMap(fn (string $table) => DB::table($table)
                            ->select('year')
                            ->distinct()
                            ->whereNotNull('year')
                            ->pluck('year'))
                        ->unique()
                        ->sortDesc()
                        ->map(fn ($year) => ['id' => (string) $year, 'label' => (string) $year])
                );
        });
    }

    private function buildMilkUtilizationYearOptions(): Collection
    {
        return Cache::remember('milk_utilization_year_options', 3600, fn () => DB::table('milk_productions')
            ->select('year')
            ->distinct()
            ->whereNotNull('year')
            ->orderByDesc('year')
            ->get()
            ->map(fn ($year) => ['id' => (string) $year->year, 'label' => (string) $year->year])
            ->values());
    }

    private function buildRedirectFilters(array $queryFilters, ?User $user): ?array
    {
        if (! $user?->isCenterScoped()) {
            return null;
        }

        $centerId = $user->center_id;
        if ($centerId === null) {
            return null;
        }

        $currentCenterIds = [];
        if (isset($queryFilters['center_id'])) {
            $rawCenterId = $queryFilters['center_id'];
            $currentCenterIds = is_array($rawCenterId)
                ? $rawCenterId
                : $this->normalizeFilterValue($rawCenterId);
        }

        if ($currentCenterIds !== [(string) $centerId]) {
            return array_merge($queryFilters, ['center_id' => $centerId]);
        }

        return null;
    }

    private function normalizeFilterValue(string $value): array
    {
        return array_filter(array_map('trim', explode(',', $value)));
    }

    private function buildReportFilters(string $reportType, Collection $centerOptions): array
    {
        return match ($reportType) {
            'milk-feeding-programs' => [
                'years' => collect([['id' => 'all', 'label' => 'All Years']])
                    ->merge(collect(range(date('Y') - 7, date('Y')))->map(fn ($year) => [
                        'id' => (string) $year,
                        'label' => (string) $year,
                    ])),
                'fundedBy' => MilkFeedingProgramFunderEnum::selectableOptions('All Funded'),
                'region' => AddressRegionEnum::selectableOptions('All Regions'),
                'milkType' => MilkTypeEnum::selectableOptions('All Types'),
                'centers' => $centerOptions,
                'modeOfProcurement' => ModeOfProcurementEnum::buildProcurementOptions(),
                'suppliers' => route('xhr.partners'),
                'provinces' => route('xhr.provinces').'?type=provinces',
                'divisions' => route('xhr.cities').'?type=mfp-divisions',
                'cities' => route('xhr.cities').'?type=cities&addressable_type=milk_feeding_program',
            ],
            'bdp' => [
                'report_templates' => collect(config('template.templates', []))
                    ->only(['date-annual', 'date-monthly'])
                    ->map(fn (array $template, string $key) => [
                        'value' => $key,
                        'label' => $template['label'] ?? Str::headline($key),
                        'variants' => $template['variants'] ?? [],
                    ])
                    ->values()
                    ->all(),
                'products' => config('reports.bdp.products', []),
                'types' => config('reports.bdp.types', []),
                'years' => $this->buildYearOptions(),
                'months' => MonthEnum::selectableOptions('All Months'),
                'programs' => ProgramEnum::selectableOptions('All Programs'),
                'centers' => $centerOptions,
                'region' => AddressRegionEnum::selectableOptions('All Regions'),
                'provinces' => route('xhr.provinces').'?type=provinces',
                'cities' => route('xhr.cities').'?type=cities',
                'partners' => route('xhr.partners'),
                'farmers' => route('xhr.farmers'),
            ],
            'milk-utilization' => [
                'years' => $this->buildMilkUtilizationYearOptions(),
                'months' => collect(MonthEnum::labels())
                    ->map(fn (string $label, int $value) => [
                        'id' => (string) $value,
                        'label' => $label,
                    ])
                    ->values()
                    ->all(),
                'centers' => $centerOptions,
            ],
            'directory' => [
                'report_templates' => collect(config('template.templates', []))
                    ->only(['centers', 'partners', 'farmers'])
                    ->map(fn (array $template, string $key) => [
                        'value' => $key,
                        'label' => $template['label'] ?? Str::headline($key),
                        'variants' => $template['variants'] ?? config("reports.directory.templates.{$key}.variants", []),
                    ])
                    ->values()
                    ->all(),
                'partnerTypes' => collect(PartnerCategoryEnum::labels())->map(fn (string $label, string $value) => [
                    'value' => $value,
                    'label' => $label,
                ])->values(),
                'businessTypes' => collect(PartnerCategoryEnum::labels())->map(fn (string $label, string $value) => [
                    'value' => $value,
                    'label' => $label,
                ])->values(),
                'centers' => $centerOptions,
                'partners' => route('xhr.partners'),
                'farmers' => route('xhr.farmers'),
                'region' => AddressRegionEnum::selectableOptions('All Regions'),
                'provinces' => route('xhr.provinces').'?type=provinces',
                'cities' => route('xhr.cities').'?type=cities',
                'districts' => route('xhr.cities').'?type=districts',
                'status' => ProfileStatusEnum::selectableOptions('All Status'),
                'projects' => ProjectProgramEnum::selectableOptions('All Projects'),
                'accreditations' => collect(DocumentEnum::labels())
                    ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                    ->values()
                    ->all(),
            ],
            default => [],
        };
    }
}
