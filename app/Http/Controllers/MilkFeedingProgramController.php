<?php

namespace App\Http\Controllers;

use App\Enums\AddressDistrictEnum;
use App\Enums\AddressRegionEnum;
use App\Enums\CenterEnum;
use App\Enums\MilkFeedingProgramFunderEnum;
use App\Enums\MilkFeedingStatusEnum;
use App\Enums\MilkSubcategoryEnum;
use App\Enums\MilkTypeEnum;
use App\Enums\ModeOfProcurementEnum;
use App\Enums\ProgramEnum;
use App\Http\Requests\StoreMilkFeedingProgramRequest;
use App\Http\Requests\UpdateMilkFeedingProgramRequest;
use App\Models\Center;
use App\Models\MilkFeedingProgram;
use App\Models\Partner;
use App\Models\Project;
use App\Support\Audits\AuditTrail;
use App\Support\Centers;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class MilkFeedingProgramController extends Controller
{
    private function buildSelectOptions(): array
    {
        $fundedByOptions = MilkFeedingProgramFunderEnum::beneficiaryName();
        $customFunders = MilkFeedingProgram::query()
            ->whereNotNull('funded_by', 'and')
            ->where('funded_by', '!=', '')
            ->distinct()
            ->orderBy('funded_by')
            ->pluck('funded_by');
        foreach ($customFunders as $customFunder) {
            $value = $customFunder instanceof \BackedEnum ? $customFunder->value : (string) $customFunder;
            $value = trim($value);
            if ($value === '' || isset($fundedByOptions[$value])) {
                continue;
            }
            $fundedByOptions[$value] = $value;
        }

        $regionOptions = AddressRegionEnum::labels();
        $customRegions = MilkFeedingProgram::query()
            ->join('addresses', 'addresses.addressable_id', '=', 'milk_feeding_programs.id', 'inner', false)
            ->where('addresses.addressable_type', '=', 'milk_feeding_program')
            ->whereNotNull('addresses.region')
            ->where('addresses.region', '!=', '')
            ->distinct()
            ->orderBy('addresses.region')
            ->pluck('addresses.region');
        foreach ($customRegions as $customRegion) {
            $value = $customRegion instanceof \BackedEnum ? $customRegion->value : (string) $customRegion;
            $value = trim($value);
            if ($value === '' || isset($regionOptions[$value])) {
                continue;
            }
            $regionOptions[$value] = $value;
        }

        $districtOptions = AddressDistrictEnum::labels();
        $customDistricts = MilkFeedingProgram::query()
            ->join('addresses', 'addresses.addressable_id', '=', 'milk_feeding_programs.id', 'inner', false)
            ->where('addresses.addressable_type', '=', 'milk_feeding_program')
            ->whereNotNull('addresses.district')
            ->where('addresses.district', '!=', '')
            ->distinct()
            ->orderBy('addresses.district')
            ->pluck('addresses.district');
        foreach ($customDistricts as $customDistrict) {
            $value = $customDistrict instanceof \BackedEnum ? $customDistrict->value : (string) $customDistrict;
            $value = trim($value);
            if ($value === '' || isset($districtOptions[$value])) {
                continue;
            }
            $districtOptions[$value] = $value;
        }

        $provinceOptions = [];
        $customProvinces = MilkFeedingProgram::query()
            ->join('addresses', 'addresses.addressable_id', '=', 'milk_feeding_programs.id', 'inner', false)
            ->where('addresses.addressable_type', '=', 'milk_feeding_program')
            ->whereNotNull('addresses.province')
            ->where('addresses.province', '!=', '')
            ->distinct()
            ->orderBy('addresses.province')
            ->pluck('addresses.province');
        foreach ($customProvinces as $customProvince) {
            $value = $customProvince instanceof \BackedEnum ? $customProvince->value : (string) $customProvince;
            $value = trim($value);
            if ($value === '' || isset($provinceOptions[$value])) {
                continue;
            }
            $provinceOptions[$value] = $value;
        }

        $municipalityOptions = [];
        $customMunicipalities = MilkFeedingProgram::query()
            ->join('addresses', 'addresses.addressable_id', '=', 'milk_feeding_programs.id', 'inner', false)
            ->where('addresses.addressable_type', '=', 'milk_feeding_program')
            ->whereNotNull('addresses.city')
            ->where('addresses.city', '!=', '')
            ->distinct()
            ->orderBy('addresses.city')
            ->pluck('addresses.city');
        foreach ($customMunicipalities as $customMunicipality) {
            $value = $customMunicipality instanceof \BackedEnum ? $customMunicipality->value : (string) $customMunicipality;
            $value = trim($value);
            if ($value === '' || isset($municipalityOptions[$value])) {
                continue;
            }
            $municipalityOptions[$value] = $value;
        }
        $divisionOptions = [];
        $customDivisions = MilkFeedingProgram::query()
            ->whereNotNull('division', 'and')
            ->where('division', '!=', '')
            ->distinct()
            ->orderBy('division')
            ->pluck('division');
        foreach ($customDivisions as $customDivision) {
            $value = $customDivision instanceof \BackedEnum ? $customDivision->value : (string) $customDivision;
            $value = trim($value);
            if ($value === '' || isset($divisionOptions[$value])) {
                continue;
            }
            $divisionOptions[$value] = $value;
        }

        $procurementOptions = ModeOfProcurementEnum::buildProcurementOptions();

        return [
            'fundedBy' => $fundedByOptions,
            'region' => $regionOptions,
            'district' => $districtOptions,
            'province' => $provinceOptions,
            'municipality' => $municipalityOptions,
            'division' => $divisionOptions,
            'modeOfProcurement' => $procurementOptions,
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $selectOptions = $this->buildSelectOptions();

        $user = $request->user();

        $milkFeedingPrograms = QueryBuilder::for(
            MilkFeedingProgram::query()
                ->when(
                    $user && $user->isCenterScoped(),
                    fn ($query) => $query->where('center_id', $user->center_id)
                )
        )
            ->with(['center', 'partner', 'project', 'address'])
            ->when(request()->get('search'), function ($query, $search) {
                $query->globalSearch($search);
            })
            ->allowedFilters([
                AllowedFilter::exact('center_id'),
                AllowedFilter::callback('center_code', function ($query, $value) {
                    $query->whereHas('center', function ($q) use ($value) {
                        $q->where('code', $value);
                    });
                }),
                AllowedFilter::exact('partner_id'),
                AllowedFilter::callback('partner_code', function ($query, $value) {
                    $query->whereHas('partner', function ($q) use ($value) {
                        $q->where('code', $value);
                    });
                }),
                AllowedFilter::exact('project_id'),
                AllowedFilter::exact('year'),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('region', function ($query, $value) {
                    $query->whereHas('address', function ($q) use ($value) {
                        $q->where('region', $value);
                    });
                }),
                AllowedFilter::callback('province', function ($query, $value) {
                    $query->whereHas('address', function ($q) use ($value) {
                        $q->where('province', $value);
                    });
                }),
                AllowedFilter::exact('division'),
                AllowedFilter::callback('municipality', function ($query, $value) {
                    $query->whereHas('address', function ($q) use ($value) {
                        $q->where('city', $value);
                    });
                }),
                AllowedFilter::exact('funded_by'),
                AllowedFilter::exact('milk_type'),
                AllowedFilter::exact('milk_subcategory'),
                AllowedFilter::exact('mode_of_procurement'),
                AllowedFilter::partial('name'),
            ])
            ->allowedSorts([
                'center_id',
                'partner_id',
                'name',
                'year',
                'funded_by',
                'region',
                'status',
                'created_at',
                'date_started',
                'date_completed',
                'raw_milk_used',
                'milk_packs',
                'milk_type',
                'total_volume_requirements',
                'whole_milk',
                'skimmed_milk',
                'sugar',
                'mode_of_procurement',
                'feeding_days',
                'number_of_beneficiaries',
                'price_per_milk_pack',
                'total_contract_amount',
                'service_fee',
                'gross_income',
                'liquidation_date',
                'moa_signing_date',
                'fund_transfer_date',
            ])
            ->defaultSort('-created_at')
            ->paginate(10)
            ->appends(request()->query());

        return Inertia::render('MilkFeedingPrograms/Index', [
            'milkFeedingPrograms' => $milkFeedingPrograms,
            'enums' => [
                'milkType' => MilkTypeEnum::labels(),
                'milkTypeSubcategories' => MilkSubcategoryEnum::labels(),
                'modeOfProcurement' => $selectOptions['modeOfProcurement'],
                'status' => MilkFeedingStatusEnum::labels(),
                'fundedBy' => $selectOptions['fundedBy'],
                'region' => $selectOptions['region'],
                'district' => $selectOptions['district'],
                'province' => $selectOptions['province'],
                'municipality' => $selectOptions['municipality'],
                'division' => $selectOptions['division'],
                'center' => Center::query()
                    ->excludingHidden(Centers::hiddenCenters())
                    ->when(
                        $user && $user->isCenterScoped(),
                        fn ($query) => $query->where('id', $user->center_id)
                    )
                    ->whereIn('code', array_keys(CenterEnum::labels()), 'and', false)
                    ->pluck('name', 'id')
                    ->toArray(),
                'partner' => Partner::query()
                    ->whereIn('id', MilkFeedingProgram::query()->select('partner_id')->distinct())
                    ->when(
                        $user && $user->isCenterScoped(),
                        fn ($query) => $query->where('center_id', $user->center_id)
                    )
                    ->orderBy('name', 'asc')
                    ->pluck('name', 'id')
                    ->toArray(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $defaultProjectId = Project::query()
            ->orderBy('id', 'asc')
            ->value('id');
        $selectOptions = $this->buildSelectOptions();

        $user = $request->user();

        if ($user && $user->isCenterScoped()) {
            $centerId = $user->center_id;
            $disableCenterField = true;
        } else {
            $centerId = null;
            $disableCenterField = false;
        }

        return Inertia::render('MilkFeedingPrograms/Create', [
            'milkFeedingProgram' => (new MilkFeedingProgram)->toArray(),
            'centers' => Center::query()
                ->excludingHidden(Centers::hiddenCenters())
                ->when(
                    $user && $user->isCenterScoped(),
                    fn ($query) => $query->where('id', $user->center_id)
                )
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'code']),
            'partners' => Partner::query()
                ->when(
                    $user && $user->isCenterScoped(),
                    fn ($query) => $query->where('center_id', $user->center_id)
                )
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'code', 'center_id']),
            'center' => $centerId ? Center::find($centerId, ['id', 'name', 'code']) : null,
            'defaultCenterId' => $centerId ? (int) $centerId : null,
            'disableCenterField' => $disableCenterField,
            'defaultProjectId' => $defaultProjectId ? (int) $defaultProjectId : null,
            'enums' => [
                'programs' => ProgramEnum::labels(),
                'milkType' => MilkTypeEnum::labels(),
                'milkTypeSubcategories' => MilkSubcategoryEnum::labels(),
                'modeOfProcurement' => $selectOptions['modeOfProcurement'],
                'status' => MilkFeedingStatusEnum::labels(),
                'fundedBy' => $selectOptions['fundedBy'],
                'region' => $selectOptions['region'],
                'district' => $selectOptions['district'],
                'province' => $selectOptions['province'],
                'municipality' => $selectOptions['municipality'],
                'division' => $selectOptions['division'],
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMilkFeedingProgramRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();

        if ($user && $user->isCenterScoped()) {
            $validated['center_id'] = $user->center_id;
        }

        $projectId = Project::query()->orderBy('id', 'asc')->value('id');
        if (! $projectId) {
            return back()->withErrors(['project_id' => 'No project found. Please create a project first.']);
        }

        $partnerId = empty($validated['partner_id']) ? null : (int) $validated['partner_id'];
        if ($partnerId) {
            $partner = Partner::query()->select(['id', 'center_id'])->find($partnerId);
            if (! $partner || (int) $partner->center_id !== (int) $validated['center_id']) {
                return back()->withErrors([
                    'partner_id' => 'Selected partner does not belong to the selected center.',
                ]);
            }
        }

        $milkFeedingProgram = MilkFeedingProgram::create([
            'project_id' => (int) $projectId,
            'center_id' => (int) $validated['center_id'],
            'partner_id' => $partnerId,
            // Keep the historical generated name for records created from this form;
            // the selectable Type is persisted in the dedicated program column.
            'name' => $validated['program'],
            'program' => $validated['program'],
            'year' => (int) $validated['year'],
            'funded_by' => $validated['funded_by'] ?? null,
            'region' => $validated['region'] ?? null,
            'district' => $validated['district'] ?? null,
            'province' => $validated['province'] ?? null,
            'municipality' => $validated['municipality'] ?? null,
            'division' => $validated['division'] ?? null,
            'milk_packs' => (int) ($validated['milk_packs'] ?? 0),
            'total_volume_requirements' => (float) ($validated['total_volume_requirements'] ?? 0),
            'raw_milk_used' => (float) ($validated['raw_milk_used'] ?? 0),
            'whole_milk' => (float) ($validated['whole_milk'] ?? 0),
            'skimmed_milk' => (float) ($validated['skimmed_milk'] ?? 0),
            'sugar' => (float) ($validated['sugar'] ?? 0),
            'milk_type' => $validated['milk_type'] ?? null,
            'milk_subcategory' => $validated['milk_subcategory'] ?? null,
            'price_per_milk_pack' => (float) ($validated['price_per_milk_pack'] ?? 0),
            'feeding_days' => (int) ($validated['feeding_days'] ?? 0),
            'number_of_beneficiaries' => (int) ($validated['number_of_beneficiaries'] ?? 0),
            'total_contract_amount' => (float) ($validated['total_contract_amount'] ?? 0),
            'service_fee' => (float) ($validated['service_fee'] ?? 0),
            'gross_income' => (float) ($validated['gross_income'] ?? 0),
            'liquidation_date' => $validated['liquidation_date'] ?? null,
            'mode_of_procurement' => $validated['mode_of_procurement'] ?? null,
            'moa_signing_date' => $validated['moa_signing_date'] ?? null,
            'fund_transfer_date' => $validated['fund_transfer_date'] ?? null,
            'date_started' => $validated['date_started'] ?? null,
            'date_completed' => $validated['date_completed'] ?? null,
            'status' => MilkFeedingStatusEnum::PLANNED->value,
        ]);

        AuditTrail::syncAddress(
            subject: $milkFeedingProgram,
            causer: $request->user(),
            description: 'Milk Feeding Program Address Created',
            data: [
                'barangay' => $validated['barangay'] ?? null,
                'city' => $validated['municipality'] ?? null,
                'province' => $validated['province'] ?? null,
                'region' => $validated['region'] ?? null,
                'district' => $validated['district'] ?? null,
            ],
            event: 'created',
        );

        return to_route('milk-feeding-programs.show', $milkFeedingProgram)
            ->with('success', 'Milk feeding program created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(MilkFeedingProgram $milkFeedingProgram)
    {
        $selectOptions = $this->buildSelectOptions();

        $milkFeedingProgram->load(['center:id,name,code', 'partner:id,name,code,center_id', 'address']);

        return Inertia::render('MilkFeedingPrograms/Show', [
            'milkFeedingProgram' => $milkFeedingProgram->toArray(),
            'enums' => [
                'programs' => ProgramEnum::labels(),
                'milkType' => MilkTypeEnum::labels(),
                'milkTypeSubcategories' => MilkSubcategoryEnum::labels(),
                'modeOfProcurement' => $selectOptions['modeOfProcurement'],
                'fundedBy' => $selectOptions['fundedBy'],
                'region' => $selectOptions['region'],
                'district' => $selectOptions['district'],
                'province' => $selectOptions['province'],
                'municipality' => $selectOptions['municipality'],
                'division' => $selectOptions['division'],
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MilkFeedingProgram $milkFeedingProgram, Request $request)
    {
        $defaultProjectId = Project::query()
            ->orderBy('id', 'asc')
            ->value('id');
        $selectOptions = $this->buildSelectOptions();

        $milkFeedingProgram->load(['center:id,name,code', 'partner:id,name,code,center_id', 'address']);

        $user = $request->user();

        return Inertia::render('MilkFeedingPrograms/Edit', [
            'milkFeedingProgram' => $milkFeedingProgram->toArray(),
            'centers' => Center::query()
                ->excludingHidden(Centers::hiddenCenters())
                ->when(
                    $user && $user->isCenterScoped(),
                    fn ($query) => $query->where('id', $user->center_id)
                )
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'code']),
            'partners' => Partner::query()
                ->when(
                    $user && $user->isCenterScoped(),
                    fn ($query) => $query->where('center_id', $user->center_id)
                )
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'code', 'center_id']),
            'defaultProjectId' => $defaultProjectId ? (int) $defaultProjectId : null,
            'enums' => [
                'programs' => ProgramEnum::labels(),
                'milkType' => MilkTypeEnum::labels(),
                'milkTypeSubcategories' => MilkSubcategoryEnum::labels(),
                'modeOfProcurement' => $selectOptions['modeOfProcurement'],
                'fundedBy' => $selectOptions['fundedBy'],
                'region' => $selectOptions['region'],
                'district' => $selectOptions['district'],
                'province' => $selectOptions['province'],
                'municipality' => $selectOptions['municipality'],
                'division' => $selectOptions['division'],
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMilkFeedingProgramRequest $request, MilkFeedingProgram $milkFeedingProgram)
    {
        $validated = $request->validated();

        $centerId = isset($validated['center_id'])
            ? (int) $validated['center_id']
            : (int) $milkFeedingProgram->center_id;

        $partnerId = array_key_exists('partner_id', $validated)
            ? (empty($validated['partner_id']) ? null : (int) $validated['partner_id'])
            : (empty($milkFeedingProgram->partner_id) ? null : (int) $milkFeedingProgram->partner_id);

        if ($partnerId) {
            $partner = Partner::query()->select(['id', 'center_id'])->find($partnerId);
            if (! $partner || (int) $partner->center_id !== $centerId) {
                return back()->withErrors([
                    'partner_id' => 'Selected partner does not belong to the selected center.',
                ]);
            }
        }

        $milkFeedingProgram->update([
            'center_id' => $centerId,
            'partner_id' => $partnerId,
            'program' => $validated['program'] ?? $milkFeedingProgram->program,
            'year' => isset($validated['year']) ? (int) $validated['year'] : (int) $milkFeedingProgram->year,
            'funded_by' => array_key_exists('funded_by', $validated) ? ($validated['funded_by'] ?: null) : $milkFeedingProgram->funded_by,
            'region' => array_key_exists('region', $validated) ? ($validated['region'] ?: null) : $milkFeedingProgram->region,
            'district' => array_key_exists('district', $validated) ? ($validated['district'] ?: null) : $milkFeedingProgram->district,
            'province' => array_key_exists('province', $validated) ? ($validated['province'] ?: null) : $milkFeedingProgram->province,
            'municipality' => array_key_exists('municipality', $validated) ? ($validated['municipality'] ?: null) : $milkFeedingProgram->municipality,
            'division' => array_key_exists('division', $validated) ? ($validated['division'] ?: null) : $milkFeedingProgram->division,
            'milk_packs' => isset($validated['milk_packs']) ? (int) $validated['milk_packs'] : $milkFeedingProgram->milk_packs,
            'total_volume_requirements' => isset($validated['total_volume_requirements']) ? (float) $validated['total_volume_requirements'] : $milkFeedingProgram->total_volume_requirements,
            'raw_milk_used' => isset($validated['raw_milk_used']) ? (float) $validated['raw_milk_used'] : $milkFeedingProgram->raw_milk_used,
            'whole_milk' => isset($validated['whole_milk']) ? (float) $validated['whole_milk'] : $milkFeedingProgram->whole_milk,
            'skimmed_milk' => isset($validated['skimmed_milk']) ? (float) $validated['skimmed_milk'] : $milkFeedingProgram->skimmed_milk,
            'sugar' => isset($validated['sugar']) ? (float) $validated['sugar'] : $milkFeedingProgram->sugar,
            'milk_type' => array_key_exists('milk_type', $validated) ? ($validated['milk_type'] ?: null) : $milkFeedingProgram->milk_type,
            'milk_subcategory' => array_key_exists('milk_subcategory', $validated) ? ($validated['milk_subcategory'] ?: null) : $milkFeedingProgram->milk_subcategory,
            'price_per_milk_pack' => isset($validated['price_per_milk_pack']) ? (float) $validated['price_per_milk_pack'] : $milkFeedingProgram->price_per_milk_pack,
            'feeding_days' => isset($validated['feeding_days']) ? (int) $validated['feeding_days'] : $milkFeedingProgram->feeding_days,
            'number_of_beneficiaries' => isset($validated['number_of_beneficiaries']) ? (int) $validated['number_of_beneficiaries'] : $milkFeedingProgram->number_of_beneficiaries,
            'total_contract_amount' => isset($validated['total_contract_amount']) ? (float) $validated['total_contract_amount'] : $milkFeedingProgram->total_contract_amount,
            'service_fee' => isset($validated['service_fee']) ? (float) $validated['service_fee'] : $milkFeedingProgram->service_fee,
            'gross_income' => isset($validated['gross_income']) ? (float) $validated['gross_income'] : $milkFeedingProgram->gross_income,
            'liquidation_date' => array_key_exists('liquidation_date', $validated) ? ($validated['liquidation_date'] ?: null) : $milkFeedingProgram->liquidation_date,
            'mode_of_procurement' => array_key_exists('mode_of_procurement', $validated) ? ($validated['mode_of_procurement'] ?: null) : $milkFeedingProgram->mode_of_procurement,
            'moa_signing_date' => array_key_exists('moa_signing_date', $validated) ? ($validated['moa_signing_date'] ?: null) : $milkFeedingProgram->moa_signing_date,
            'fund_transfer_date' => array_key_exists('fund_transfer_date', $validated) ? ($validated['fund_transfer_date'] ?: null) : $milkFeedingProgram->fund_transfer_date,
            'date_started' => array_key_exists('date_started', $validated) ? ($validated['date_started'] ?: null) : $milkFeedingProgram->date_started,
            'date_completed' => array_key_exists('date_completed', $validated) ? ($validated['date_completed'] ?: null) : $milkFeedingProgram->date_completed,
        ]);

        $addressData = [
            'barangay' => $validated['barangay'] ?? null,
            'city' => $validated['municipality'] ?? null,
            'province' => $validated['province'] ?? null,
            'region' => $validated['region'] ?? null,
            'district' => $validated['district'] ?? null,
        ];

        AuditTrail::syncAddress(
            subject: $milkFeedingProgram,
            causer: $request->user(),
            description: 'Milk Feeding Program Address Updated',
            data: $addressData,
        );

        return to_route('milk-feeding-programs.show', $milkFeedingProgram)
            ->with('success', 'Milk feeding program updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MilkFeedingProgram $milkFeedingProgram)
    {
        MilkFeedingProgram::destroy($milkFeedingProgram->getKey());

        return to_route('milk-feeding-programs.index')
            ->with('success', 'Milk feeding program deleted successfully.');
    }
}
