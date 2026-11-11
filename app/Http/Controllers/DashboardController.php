<?php

namespace App\Http\Controllers;

use App\Enums\CenterEnum;
use App\Enums\DocumentEnum;
use App\Enums\ProgramEnum;
use App\Models\Center;
use App\Models\CenterProductionGoal;
use App\Models\Farmer;
use App\Models\Partner;
use App\Queries\Dashboard\AnimalInventoryCountQuery;
use App\Queries\Dashboard\CsoCountQuery;
use App\Queries\Dashboard\FdaLtoCountQuery;
use App\Queries\Dashboard\FfedisCountQuery;
use App\Queries\Dashboard\KnpCountQuery;
use App\Queries\Dashboard\ManureProductionQuery;
use App\Queries\Dashboard\MarketingCountQuery;
use App\Queries\Dashboard\MeatProductionQuery;
use App\Queries\Dashboard\MilkProductionQuery;
use App\Queries\Dashboard\NdaLtoCountQuery;
use App\Queries\Dashboard\ProcessingCountQuery;
use App\Queries\Dashboard\RsbsaCountQuery;
use App\Support\Centers;
use App\Support\Date;
use App\Support\Filters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {

        $user = request()->user();
        if ($user && $user->isCenterScoped()) {
            $centerId = $user->center_id;
        } else {
            $centerId = isset($request->all()['filter']['center_id'])
                ? (int) $request->all()['filter']['center_id']
                : null;
        }

        $filters = $request->all()['filter'] ?? [];

        $productionDateRange = Date::resolveDateRange($filters, 'production');
        $milkFeedingDateRange = Date::resolveDateRange($filters, 'milkFeeding');
        $servicesDateRange = Date::resolveDateRange($filters, 'services');
        if (! isset($filters['servicesYear']) && ! isset($filters['servicesMonth']) && ! isset($filters['servicesDateRange'])) {
            $servicesDateRange = $productionDateRange;
        }

        $programValue = $request->query('program')
            ?? $request->all()['filter']['program'] ?? session('_program', ProgramEnum::CBED->value);

        // `filter[program]` may be "all" — normalize to page program for `_program`/session.
        if ($programValue === Filters::ALL_PROGRAM) {
            $programValue = ProgramEnum::CBED->value;
        }

        $programEnum = ProgramEnum::tryFrom($programValue) ?? ProgramEnum::DEFAULT;

        // Persist so HandleInertiaRequests fallback (`filter[program] ?? session(_program)`)
        // and non-dashboard pages stay in sync without requiring `?program=` on every visit.
        session(['_program' => $programEnum->value]);

        // Data aggregation program filter: 'all' (IH + CBED combined), a specific
        // program, or unset (defaults to the page program)
        $dataProgramEnum = Filters::resolveDataProgram(
            $filters[Filters::PROGRAM] ?? null,
            $programEnum
        );

        $centerIds = Center::pluck('id');

        // Preload all value chain data in single queries
        $valueChainCounts = DB::table('centers')
            ->leftJoin('production_units', 'centers.id', '=', 'production_units.center_id')
            ->leftJoin('processing_units', 'centers.id', '=', 'processing_units.center_id')
            ->leftJoin('consolidation_units', 'centers.id', '=', 'consolidation_units.center_id')
            ->leftJoin('marketing_units', 'centers.id', '=', 'marketing_units.center_id')
            ->select('centers.id', DB::raw('
                COUNT(DISTINCT production_units.id) +
                COUNT(DISTINCT processing_units.id) +
                COUNT(DISTINCT consolidation_units.id) +
                COUNT(DISTINCT marketing_units.id) as total
            '))
            ->groupBy('centers.id')
            ->pluck('total', 'id');

        $staffCounts = DB::table('centers')
            ->leftJoin('production_units', 'centers.id', '=', 'production_units.center_id')
            ->leftJoin('processing_units', 'centers.id', '=', 'processing_units.center_id')
            ->leftJoin('consolidation_units', 'centers.id', '=', 'consolidation_units.center_id')
            ->leftJoin('marketing_units', 'centers.id', '=', 'marketing_units.center_id')
            ->select('centers.id', DB::raw('
                COALESCE(SUM(production_units.number_of_staff_female), 0) +
                COALESCE(SUM(processing_units.number_of_staff_male), 0) +
                COALESCE(SUM(consolidation_units.number_of_staff_male), 0) +
                COALESCE(SUM(marketing_units.number_of_staff_male), 0) as total
            '))
            ->groupBy('centers.id')
            ->pluck('total', 'id');

        $avgSalaries = DB::table('centers')
            ->leftJoin('production_units', 'centers.id', '=', 'production_units.center_id')
            ->leftJoin('processing_units', 'centers.id', '=', 'processing_units.center_id')
            ->leftJoin('consolidation_units', 'centers.id', '=', 'consolidation_units.center_id')
            ->leftJoin('marketing_units', 'centers.id', '=', 'marketing_units.center_id')
            ->select('centers.id', DB::raw('
                (COALESCE(AVG(production_units.avg_salary_of_staff), 0) +
                COALESCE(AVG(processing_units.avg_salary_of_staff), 0) +
                COALESCE(AVG(consolidation_units.avg_salary_of_staff), 0) +
                COALESCE(AVG(marketing_units.avg_salary_of_staff), 0)) as avg
            '))
            ->groupBy('centers.id')
            ->pluck('avg', 'id');

        $partnerCounts = Partner::whereIn('center_id', $centerIds)
            ->select('center_id', DB::raw('COUNT(*) as count'))
            ->groupBy('center_id')
            ->pluck('count', 'center_id');

        $farmerCounts = Farmer::whereIn('center_id', $centerIds)
            ->select('center_id', DB::raw('COUNT(*) as count'))
            ->groupBy('center_id')
            ->pluck('count', 'center_id');

        $isCenterAdmin = $user && $user->isCenterScoped();

        // A center admin may only ever see their own center, so restrict the
        // center collection (and everything derived from it) to that row.
        $centerQuery = Center::query()->with('address');

        if ($isCenterAdmin && $centerId) {
            $centerQuery->whereKey($centerId);
        }

        $centers = $centerQuery
            ->get()
            ->map(function ($center) use ($valueChainCounts, $staffCounts, $avgSalaries, $partnerCounts, $farmerCounts) {
                $valueChainCount = $valueChainCounts[$center->id] ?? 0;

                return [
                    ...$center->except('address'),
                    ...[
                        'latitude' => $center->address?->latitude,
                        'longitude' => $center->address?->longitude,
                        'address' => $center->address?->fullAddress,
                    ],
                    'status' => $center->status,
                    'color' => CenterEnum::tryFrom(Str::lower($center->code))?->color() ?? 'gray',
                    'value_chain_count' => $valueChainCount,
                    'value_chain_staff_count' => $staffCounts[$center->id] ?? 0,
                    'value_chain_avg_salary' => $valueChainCount > 0 ? ($avgSalaries[$center->id] ?? 0) / 4 : 0,
                    'total_partners' => $partnerCounts[$center->id] ?? 0,
                    'total_farmers' => $farmerCounts[$center->id] ?? 0,
                ];
            });

        $centerRegions = DB::table('addresses')
            ->join('centers', 'addresses.center_id', '=', 'centers.id')
            ->when($isCenterAdmin && $centerId, fn ($query) => $query->where('centers.id', $centerId))
            ->select([
                DB::raw('centers.code as center_code'),
                DB::raw('GROUP_CONCAT(DISTINCT(addresses.region)) as regions'),
            ])->groupBy('centers.code')
            ->get()
            ->mapWithKeys(function ($item) {
                return [
                    Str::lower($item->center_code) => [
                        'regions' => explode(',', $item->regions),
                        'color' => CenterEnum::tryFrom(Str::lower($item->center_code))?->color() ?? 'gray',
                    ],
                ];
            })->toArray();

        $depEdMilkFeedingData = resolve('App\Queries\Dashboard\MilkFeedingQuery')->execute(
            agency: 'depEd',
            centerId: $centerId,
            dateRange: $milkFeedingDateRange,
            program: $dataProgramEnum?->value
        )->first();

        // dd($depEdMilkFeedingData);
        $dwsdMilkFeedingData = resolve('App\Queries\Dashboard\MilkFeedingQuery')->execute(
            agency: 'dswd',
            centerId: $centerId,
            dateRange: $milkFeedingDateRange,
            program: $dataProgramEnum?->value
        )->first();

        $lguMilkFeedingData = resolve('App\Queries\Dashboard\MilkFeedingQuery')->execute(
            agency: 'lgu',
            centerId: $centerId,
            dateRange: $milkFeedingDateRange,
            program: $dataProgramEnum?->value
        )->first();

        $ldsMilkFeedingData = resolve('App\Queries\Dashboard\MilkFeedingQuery')->execute(
            agency: 'lds',
            centerId: $centerId,
            dateRange: $milkFeedingDateRange,
            program: $dataProgramEnum?->value
        )->first();

        $buffaloProducts = $this->buffaloProductTotals($centerId, $productionDateRange, $dataProgramEnum);
        $previousBuffaloProducts = $this->buffaloProductTotals(
            $centerId,
            Date::previousPeriod($productionDateRange),
            $dataProgramEnum
        );

        $totalMilkVolumeCbed = $centerId
            ? (float) resolve(MilkProductionQuery::class)
                ->execute(centerId: $centerId, dateRange: $productionDateRange, program: ProgramEnum::CBED)
                ->get('total_volume_produced')
            : $buffaloProducts['totalMilkVolume'];

        $documentCounts = resolve('App\Queries\Dashboard\DocumentCountQuery')->execute(centerId: $centerId, program: $programEnum);

        $productionGoal = $centerId
            ? CenterProductionGoal::query()->where('center_id', $centerId)
                ->where('program', $programEnum)
                ->where('year', now()->year)
                ->first()
            ?? CenterProductionGoal::query()->where('center_id', $centerId)
                ->where('program', $programEnum)
                ->orderByDesc('year')
                ->first()
            : null;

        $years = Date::distinctProductionYears();
        $milkFeedingYears = Date::distinctMilkFeedingYears();

        $servicesCounts = $programEnum === ProgramEnum::CBED
            ? resolve('App\Queries\Dashboard\ServicesCountQuery')->execute(centerId: $centerId, dateRange: $servicesDateRange)
            : [
                'production_support_services' => 0,
                'market_development_services' => 0,
                'iec_clients' => 0,
                'clients_trained' => 0,
            ];

        return Inertia::render('Dashboard', [
            'center_regions' => $centerRegions,
            'centers' => $centers,
            'hidden_centers' => Centers::hiddenCenters($dataProgramEnum?->value),
            '_program' => $programEnum->value,
            'total_fcas' => resolve('App\Queries\Dashboard\FamersFCACountQuery')->execute(centerId: $centerId, program: $dataProgramEnum),
            'total_farmers' => resolve('App\Queries\Dashboard\FarmersCountQuery')->execute(centerId: $centerId),
            'total_production_staff' => resolve('App\Queries\Dashboard\ProductionStaffCountQuery')->execute(centerId: $centerId),
            'total_animals' => resolve(AnimalInventoryCountQuery::class)->execute(
                $programEnum === ProgramEnum::IH ? 'centers' : ['farmers', 'partners'],
                $centerId,
                $dataProgramEnum
            ),
            'cows_in_milk_line' => 0,
            'production_support_services' => $servicesCounts['production_support_services'],
            'market_development_services' => $servicesCounts['market_development_services'],
            'iec_clients' => $servicesCounts['iec_clients'],
            'clients_trained' => $servicesCounts['clients_trained'],
            'total_milk_volume' => $buffaloProducts['totalMilkVolume'],
            'total_meat_produced' => $buffaloProducts['totalMeatProduced'],
            'total_manure_produced' => $buffaloProducts['totalManureProduced'],
            'total_milk_value' => $buffaloProducts['totalMilkValue'],
            'total_meat_value' => $buffaloProducts['totalMeatValue'],
            'total_manure_value' => $buffaloProducts['totalManureValue'],
            'previous_buffalo_totals' => $previousBuffaloProducts,
            'selected_production_year' => is_int($productionDateRange)
                ? $productionDateRange
                : (int) date('Y', strtotime($productionDateRange[0])),
            'production_goal_milk' => (float) ($productionGoal?->milk_production_goal ?? 0),
            'production_goal_meat' => (float) ($productionGoal?->meat_meat_equivalent_goal ?? 0),
            'production_goal_manure' => (float) ($productionGoal?->manure_based_goal ?? 0),
            'total_milk_volume_cbed' => $totalMilkVolumeCbed,
            'deped_total_beneficiaries' => (int) ($depEdMilkFeedingData->total_beneficiaries ?? 0),
            'deped_milk_packs_distributed' => (int) ($depEdMilkFeedingData->total_milk_packs ?? 0),
            'deped_no_of_sdos_engaged' => (int) ($depEdMilkFeedingData->total_sdos_engaged ?? 0),
            'deped_no_of_cooperatives_engaged' => (int) ($depEdMilkFeedingData->total_cooperatives_engaged ?? 0),
            'deped_gross_income' => (int) $depEdMilkFeedingData->gross_income ?? 0,
            'dswd_total_beneficiaries' => (int) $dwsdMilkFeedingData->total_beneficiaries ?? 0,
            'dswd_milk_packs_distributed' => (int) $dwsdMilkFeedingData->total_milk_packs ?? 0,
            'dswd_no_of_lgus_engaged' => (int) $dwsdMilkFeedingData->total_lgu_engaged ?? 0,
            'dswd_no_of_cooperatives_engaged' => (int) $dwsdMilkFeedingData->total_cooperatives_engaged ?? 0,
            'dswd_gross_income' => (int) $dwsdMilkFeedingData->gross_income ?? 0,
            'lgu_total_beneficiaries' => (int) $lguMilkFeedingData->total_beneficiaries ?? 0,
            'lgu_milk_packs_distributed' => (int) $lguMilkFeedingData->total_milk_packs ?? 0,
            'lgu_no_of_lgus_engaged' => (int) $lguMilkFeedingData->total_lgu_engaged ?? 0,
            'lgu_no_of_cooperatives_engaged' => (int) $lguMilkFeedingData->total_cooperatives_engaged ?? 0,
            'lgu_gross_income' => (int) $lguMilkFeedingData->gross_income ?? 0,
            'lds_total_beneficiaries' => (int) $ldsMilkFeedingData->total_beneficiaries ?? 0,
            'lds_milk_packs_distributed' => (int) $ldsMilkFeedingData->total_milk_packs ?? 0,
            'lds_no_of_lgus_engaged' => (int) $ldsMilkFeedingData->total_lgu_engaged ?? 0,
            'lds_no_of_cooperatives_engaged' => (int) $ldsMilkFeedingData->total_cooperatives_engaged ?? 0,
            'lds_gross_income' => (int) $ldsMilkFeedingData->gross_income ?? 0,
            'processing_enterprises' => resolve(ProcessingCountQuery::class)->execute(centerId: $centerId, program: $dataProgramEnum),
            'market_enterprises' => resolve(MarketingCountQuery::class)->execute(centerId: $centerId, program: $dataProgramEnum),
            'dairy_box_enterprises' => resolve('App\Queries\Dashboard\DairyBoxCountQuery')->execute(centerId: $centerId, program: $dataProgramEnum),
            'knp_enterprises' => resolve(KnpCountQuery::class)->execute(centerId: $centerId, program: $dataProgramEnum),
            'cso_compliance' => resolve(CsoCountQuery::class)->execute(centerId: $centerId, program: $dataProgramEnum?->value),
            'fda_lto_compliance' => resolve(FdaLtoCountQuery::class)->execute(centerId: $centerId, program: $dataProgramEnum?->value),
            'nda_lto_compliance' => resolve(NdaLtoCountQuery::class)->execute(centerId: $centerId, program: $dataProgramEnum?->value),
            'ffedis_compliance' => resolve(FfedisCountQuery::class)->execute(centerId: $centerId, program: $dataProgramEnum?->value),
            'rsbsa_compliance' => resolve(RsbsaCountQuery::class)->execute(centerId: $centerId, program: $dataProgramEnum?->value),
            'production_years' => $years,
            'milk_feeding_years' => $milkFeedingYears,
            'certification_labels' => DocumentEnum::labels(),
            'certification_property_mapping' => DocumentEnum::getPropertyMapping(),
            ...$documentCounts,
        ]);
    }

    private function buffaloProductTotals(?int $centerId, mixed $dateRange, ?ProgramEnum $program): array
    {
        $milk = resolve(MilkProductionQuery::class)
            ->execute(centerId: $centerId, dateRange: $dateRange, program: $program);
        $meat = resolve(MeatProductionQuery::class)
            ->execute(centerId: $centerId, dateRange: $dateRange, program: $program);
        $manure = resolve(ManureProductionQuery::class)
            ->execute(centerId: $centerId, dateRange: $dateRange, program: $program);

        return [
            'totalMilkVolume' => (float) $milk->get('total_volume_produced'),
            'totalMeatProduced' => (float) $meat->get('total_volume_produced'),
            'totalManureProduced' => (float) $manure->get('total_volume_produced'),
            'totalMilkValue' => (float) $milk->get('total_sales_value_added'),
            'totalMeatValue' => (float) $meat->get('total_sales_value_added'),
            'totalManureValue' => (float) $manure->get('total_sales_value_added'),
        ];
    }
}
