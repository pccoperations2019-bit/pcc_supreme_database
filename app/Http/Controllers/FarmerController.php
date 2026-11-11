<?php

namespace App\Http\Controllers;

use App\Actions\Document\CreateDocumentAction;
use App\Actions\Tag\CreateTagAction;
use App\Enums\AddressDistrictEnum;
use App\Enums\AddressRegionEnum;
use App\Enums\CertificateStatusEnum;
use App\Enums\CivilStatusEnum;
use App\Enums\ComplianceStatusEnum;
use App\Enums\DocumentEnum;
use App\Enums\EducationalAttainmentEnum;
use App\Enums\EntrepreneurTypeEnum;
use App\Enums\FarmBusinessRegistrationEnum;
use App\Enums\FarmerCategoryEnum;
use App\Enums\FarmRegistrationStatusEnum;
use App\Enums\FeedingManagementEnum;
use App\Enums\IncomeSourceEnum;
use App\Enums\ManagementSystemEnum;
use App\Enums\MembershipTypeEnum;
use App\Enums\ProfileStatusEnum;
use App\Enums\ProjectProgramEnum;
use App\Enums\TvTypeEnum;
use App\Http\Requests\StoreFarmerRequest;
use App\Http\Requests\UpdateFarmerRequest;
use App\Models\Center;
use App\Models\Document;
use App\Models\Farmer;
use App\Models\Partner;
use App\Models\Project;
use App\Sorts\RegionSort;
use App\Support\Audits\AuditTrail;
use App\Support\Centers;
use App\Support\Tenancy\TenantContext;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class FarmerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $farmers = QueryBuilder::for(Farmer::class)
            ->select(['id', 'code', 'first_name', 'last_name', 'middle_name', 'nickname', 'center_id', 'partner_id', 'status', 'age', 'birthday', 'gender', 'contact_number', 'created_at'])
            ->when(
                $user && $user->isCenterScoped(),
                fn ($query) => $query->where('center_id', $user->center_id)
            )
            ->with([
                'address',
                'partner:id,name,code',
                'center:id,name,code',
            ])
            ->when(request()->get('search'), fn ($query, $search) => $query->globalSearch($search))
            ->allowedFilters([
                AllowedFilter::callback('key', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('first_name', 'like', "%{$value}%")
                            ->orWhere('last_name', 'like', "%{$value}%")
                            ->orWhere('code', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::partial('first_name'),
                AllowedFilter::partial('last_name'),
                AllowedFilter::exact('center_id'),
                AllowedFilter::exact('partner_id'),
                AllowedFilter::exact('status'),
            ])
            ->allowedSorts([
                'code',
                'first_name',
                'last_name',
                'status',
                AllowedSort::custom('region', new RegionSort),
                'created_at',
            ])
            ->defaultSort(AllowedSort::custom('region', new RegionSort))
            ->paginate(10)
            ->appends(request()->query());

        return Inertia::render('Farmers/Index', [
            'farmers' => $farmers,
            'enums' => [
                'statuses' => ProfileStatusEnum::labels(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $user = $request->user();
        $tenant = resolve(TenantContext::class);

        if ($user && $user->isCenterScoped()) {
            $centerId = $user->center_id;
            $disableCenterField = true;
        } else {
            $centerId = $tenant->selectedCenterId();
            $disableCenterField = false;
        }

        $centers = Center::query()
            ->excludingHidden(Centers::hiddenCenters())
            ->when($tenant->agencyId(), fn ($q) => $q->where('agency_id', $tenant->agencyId()))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $defaultCenterId = $centerId ?? $centers->first()?->id;

        $partners = Partner::query()
            ->when($tenant->agencyId(), function ($q) use ($tenant) {
                $q->whereHas('center', fn ($cq) => $cq->where('agency_id', $tenant->agencyId()));
            })
            ->when(
                $user && $user->isCenterScoped(),
                fn ($q) => $q->where('center_id', $user->center_id)
            )
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'center_id']);

        $projects = Project::query()->orderBy('program')->get(['id', 'program'])->mapWithKeys(function ($p) {
            $programValue = $p->program instanceof BackedEnum ? $p->program->value : $p->program;
            $label = ProjectProgramEnum::labels()[$programValue] ?? strtoupper((string) $programValue);

            return [$p->id => $label];
        })->toArray();

        return Inertia::render('Farmers/Create', [
            'farmer' => array_merge((new Farmer)->toArray(), ['photo_url' => null]),
            'documents' => [],
            'enums' => [
                'document' => DocumentEnum::labels(),
                'complianceStatuses' => ComplianceStatusEnum::options(),
                'addressRegion' => AddressRegionEnum::labels(),
                'addressDistrict' => AddressDistrictEnum::labels(),
                'farmerCategory' => FarmerCategoryEnum::labels(),
                'entrepreneurType' => EntrepreneurTypeEnum::labels(),
                'civilStatus' => CivilStatusEnum::labels(),
                'projectCategory' => $this->projectCategoryOptions(),
                'educationalAttainment' => EducationalAttainmentEnum::labels(),
                'certificateStatus' => CertificateStatusEnum::labels(),
                'managementSystem' => ManagementSystemEnum::labels(),
                'feedingManagement' => FeedingManagementEnum::labels(),
                'incomeSource' => IncomeSourceEnum::labels(),
                'membershipType' => MembershipTypeEnum::labels(),
                'tvType' => TvTypeEnum::labels(),
                'farmBusinessRegistration' => FarmBusinessRegistrationEnum::labels(),
                'farmRegistrationStatus' => $this->farmRegistrationStatusOptions(),
            ],
            'centers' => $centers,
            'partners' => $partners,
            'projects' => $projects,
            'defaultCenterId' => $defaultCenterId ? (int) $defaultCenterId : null,
            'disableCenterField' => $disableCenterField,
        ]);
    }

    /**
     * Search farmers for AJAX requests.
     */
    public function search(Request $request)
    {
        $user = $request->user();
        $key = $request->input('key', '');

        $farmers = Farmer::query()
            ->when(
                $user && $user->isCenterScoped(),
                fn ($query) => $query->where('center_id', $user->center_id)
            )
            ->when($key, fn ($query, $key) => $query->globalSearch($key))
            ->limit(20)
            ->get()
            ->map(function ($farmer) {
                return [
                    'value' => $farmer->id,
                    'label' => $farmer->first_name.' '.$farmer->last_name,
                ];
            });

        return response()->json($farmers);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(
        StoreFarmerRequest $request,
        CreateDocumentAction $createDocumentAction,
        CreateTagAction $createTagAction,
    ) {
        $tenant = resolve(TenantContext::class);
        $validated = $request->validated();

        $center = Center::query()
            ->when($tenant->agencyId(), fn ($q) => $q->where('agency_id', $tenant->agencyId()))
            ->find((int) ($validated['center_id'] ?? 0));
        if (! $center) {
            abort(403);
        }

        $partner = null;
        if (! empty($validated['partner_id'] ?? null)) {
            $partner = Partner::query()
                ->where('center_id', $center->id)
                ->find((int) ($validated['partner_id'] ?? 0));
        }

        $farmer = new Farmer;
        $photoPath = $farmer->storePhoto($request->file('photo'));

        $dateOfBirth = empty($validated['birthday']) ? null : Date::parse($validated['birthday']);
        $age = $dateOfBirth?->age;

        $data = [
            'center_id' => (int) $center->id,
            'partner_id' => $partner?->id,
            'birthday' => $dateOfBirth,
            'photo' => $photoPath,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'nickname' => $validated['nickname'] ?? null,
            'contact_number' => $validated['contact_number'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'civil_status' => $validated['civil_status'] ?? null,
            'age' => $age,
            'position' => $validated['position'] ?? null,
            'year_involvement_in_business' => $validated['year_involvement_in_business'] ?? null,
            'family_member_attending_school_count' => $validated['family_member_attending_school_count'] ?? null,
            'female_count' => $validated['female_count'] ?? null,
            'male_count' => $validated['male_count'] ?? null,
            'is_pwd' => $validated['is_pwd'] ?? false,
            'is_4ps' => $validated['is_4ps'] ?? false,
            'is_ips' => $validated['is_ips'] ?? false,
            'is_indigent' => $validated['is_indigent'] ?? false,
            'indigenous_group' => $validated['indigenous_group'] ?? null,
            'educational_attainment' => $validated['educational_attainment'] ?? null,
            'ndalto_certification_status' => $validated['ndalto_certification_status'] ?? null,
            'ndalto_certification' => $validated['ndalto_certification'] ?? null,
            'type_of_entrepreneur' => $validated['type_of_entrepreneur'] ?? null,
            'rsbsa_cert_status' => $validated['rsbsa_cert_status'] ?? null,
            'rsbsa_certificate_of_registration' => $validated['rsbsa_certificate_of_registration'] ?? null,
            'if_producer_please_specify' => $validated['if_producer_please_specify'] ?? null,
            'involved_in_buffalo_farming' => $validated['involved_in_buffalo_farming'] ?? false,
            'how_many_in_family_are_involved_in_buffalo_farming' => $validated['how_many_in_family_are_involved_in_buffalo_farming'] ?? null,
            'are_you_the_head_of_the_family' => $validated['are_you_the_head_of_the_family'] ?? false,
            'if_no_highest_educational_attainment_of_head_of_family' => $validated['if_no_highest_educational_attainment_of_head_of_family'] ?? null,
            'challenges_or_problems_encountered_in_dairy_farming' => $validated['challenges_or_problems_encountered_in_dairy_farming'] ?? null,
            'main_source_of_income' => $validated['main_source_of_income'] ?? null,
            'main_annual_net_income' => $validated['main_annual_net_income'] ?? null,
            'secondary_source_of_income' => $validated['secondary_source_of_income'] ?? null,
            'secondary_annual_net_income' => $validated['secondary_annual_net_income'] ?? null,
            'tertiary_source_of_income' => $validated['tertiary_source_of_income'] ?? null,
            'tertiary_annual_net_income' => $validated['tertiary_annual_net_income'] ?? null,
            'quaternary_source_of_income' => $validated['quaternary_source_of_income'] ?? null,
            'quaternary_annual_net_income' => $validated['quaternary_annual_net_income'] ?? null,
            'avg_net_income' => $validated['avg_net_income'] ?? null,
            'total_net_income' => $validated['total_net_income'] ?? null,
            'staff_count_male' => $validated['staff_count_male'] ?? $validated['staff_count'] ?? null,
            'staff_count_female' => $validated['staff_count_female'] ?? null,
            'status' => $validated['status'] ?? ProfileStatusEnum::DEFAULT->value,
            'date_of_interview' => empty($validated['date_of_interview'] ?? null)
                ? null
                : Date::parse($validated['date_of_interview']),
            'date_of_enrollment' => empty($validated['date_of_enrollment']) ? null : Date::parse($validated['date_of_enrollment']),
            'operation_in_charge' => $validated['operation_in_charge'] ?? null,
            'is_assisted' => $validated['is_assisted'] ?? null,
            'project_category' => $validated['project_category'] ?? null,
            'has_personal_cellphone' => $validated['has_personal_cellphone'] ?? null,
            'purok_zone' => $validated['purok_zone'] ?? null,
            'zip_code' => $validated['zip_code'] ?? null,
            'metadata' => $validated['additional_details'] ?? null,
            'other_livestock_details' => $validated['other_livestock_details'] ?? null,
            'source_of_income_details' => $validated['source_of_income_details'] ?? null,
            'animal_inventory' => $validated['animal_inventory'] ?? null,
            'animal_ownership' => $validated['animal_ownership'] ?? null,
            'feeding_management' => $validated['feeding_management'] ?? null,
            'membership_in_organization' => $validated['membership_in_organization'] ?? null,
            'name_of_organization' => $validated['name_of_organization'] ?? null,
            'tv_type' => $validated['tv_type'] ?? TvTypeEnum::DEFAULT->value,
            'farm_region' => $validated['farm_region'] ?? null,
            'farm_province' => $validated['farm_province'] ?? null,
            'farm_municipality_city' => $validated['farm_municipality_city'] ?? null,
            'farm_barangay' => $validated['farm_barangay'] ?? null,
            'farm_purok_zone' => $validated['farm_purok_zone'] ?? null,
            'farm_district' => $validated['farm_district'] ?? null,
            'farm_street' => $validated['farm_street'] ?? null,
            'proposed_farm_name' => $validated['proposed_farm_name'] ?? null,
            'type_of_farm_business_registration' => $validated['type_of_farm_business_registration'] ?? null,
            'status_of_registration' => $validated['status_of_registration'] ?? null,
            'farm_registration_number' => $validated['farm_registration_number'] ?? null,
            'has_rsbsa' => $validated['has_rsbsa'] ?? false,
            'year_farm_established' => $validated['year_farm_established'] ?? null,
            'is_drylot' => $validated['is_drylot'] ?? false,
            'duration_of_stocking' => $validated['duration_of_stocking'] ?? null,
            'is_applied_in_the_field' => $validated['is_applied_in_the_field'] ?? false,
            'is_dry_rice_field' => $validated['is_dry_rice_field'] ?? false,
            'is_dry_vegetable' => $validated['is_dry_vegetable'] ?? false,
            'is_vermicomposting' => $validated['is_vermicomposting'] ?? false,
            'is_pasture_range_paddock' => $validated['is_pasture_range_paddock'] ?? false,
            'is_daily_spread' => $validated['is_daily_spread'] ?? false,
            'is_storage_bulking' => $validated['is_storage_bulking'] ?? false,
            'is_liquid_slurry' => $validated['is_liquid_slurry'] ?? false,
            'is_anaerobic_digester' => $validated['is_anaerobic_digester'] ?? false,
            'is_solid_storage_covered' => $validated['is_solid_storage_covered'] ?? false,
            'is_solid_storage_additives' => $validated['is_solid_storage_additives'] ?? false,
            'is_uncovered_anaerobic_lagoon' => $validated['is_uncovered_anaerobic_lagoon'] ?? false,
            'is_twice_a_day_milking' => $validated['is_twice_a_day_milking'] ?? false,
            'daily_milk_production_min' => $validated['daily_milk_production_min'] ?? null,
            'daily_milk_production_max' => $validated['daily_milk_production_max'] ?? null,
            'milk_price_per_liter_min' => $validated['milk_price_per_liter_min'] ?? null,
            'milk_price_per_liter_max' => $validated['milk_price_per_liter_max'] ?? null,
            'milk_selling_locations' => $validated['milk_selling_locations'] ?? null,
            'milk_selling_restaurant_specify' => $validated['milk_selling_restaurant_specify'] ?? null,
            'milk_selling_processor_specify' => $validated['milk_selling_processor_specify'] ?? null,
            'milk_selling_collector_name' => $validated['milk_selling_collector_name'] ?? null,
            'milk_selling_others_specify' => $validated['milk_selling_others_specify'] ?? null,
            'assistance_needed' => $validated['assistance_needed'] ?? null,
            'pcc_assets_latitude' => $validated['pcc_assets_latitude'] ?? null,
            'pcc_assets_longitude' => $validated['pcc_assets_longitude'] ?? null,
            'pcc_projects_assets' => $validated['pcc_projects_assets'] ?? null,
            'farm_name' => $validated['farm_name'] ?? null,
            'management_system' => $validated['management_system'] ?? null,
            'ave_monthly_salary' => $validated['ave_monthly_salary'] ?? null,
            'status_google_map' => $validated['status_google_map'] ?? null,
            'google_map_link' => $validated['google_map_link'] ?? null,
        ];

        $addressData = [
            'street' => $validated['street'] ?? null,
            'barangay' => $validated['barangay'] ?? null,
            'city' => $validated['municipality'] ?? null,
            'province' => $validated['province'] ?? null,
            'region' => $validated['region'] ?? null,
            'district' => $validated['district'] ?? null,
            'latitude' => $validated['gps_latitude'] ?? null,
            'longitude' => $validated['gps_longitude'] ?? null,
        ];

        $code = $validated['code'];

        $saved = DB::transaction(function () use (
            $data,
            $code,
            $addressData,
            $validated,
            $request,
            $createDocumentAction,
            $createTagAction,
        ) {
            $farmer = Farmer::create(['code' => $code] + $data);

            $farmer->address()->create($addressData);

            $geotagImagePath = $farmer->storeGeotagImage($request->file('geotag_image_file'));
            $entrepreneurImagePath = $farmer->storeEntrepreneurImage($request->file('entrepreneur_image_file'));
            $enterpriseImagePath = $farmer->storeEnterpriseImage($request->file('enterprise_image_file'));

            if ($geotagImagePath || $entrepreneurImagePath || $enterpriseImagePath) {
                // The audit trail records its "before" snapshot from the model's
                // own original attributes. A model created moments ago in this same
                // request only holds the columns that were sent, so every
                // database default would read as a change. Refreshing first makes
                // the snapshot the row as it actually is, so the entry records
                // only the images that were just stored.
                $farmer->refresh();

                $farmer->update(array_filter([
                    'geotag_image' => $geotagImagePath,
                    'entrepreneur_image' => $entrepreneurImagePath,
                    'enterprise_image' => $enterpriseImagePath,
                ]));
            }

            $this->createDocuments(
                $farmer,
                $validated,
                $request->user()->id,
                $createDocumentAction,
                $createTagAction,
            );

            if (! empty($validated['project_ids'])) {
                $farmer->projects()->sync($validated['project_ids']);
            }

            return $farmer;
        });

        return to_route('farmers.edit', ['farmer' => $saved->id])
            ->with('success', 'Farmer created successfully.');
    }

    /**
     * Create documents from a one-time create payload, tagged to the new farmer.
     */
    private function createDocuments(
        Farmer $farmer,
        array $validated,
        int $userId,
        CreateDocumentAction $createDocumentAction,
        CreateTagAction $createTagAction,
    ): void {
        foreach ($validated['documents'] ?? [] as $data) {
            $document = $createDocumentAction->execute(
                title: $data['title'],
                fileName: $data['file_name'],
                fileKey: $data['file_key'],
                fileType: $data['file_type'] ?? null,
                fileSize: (int) ($data['file_size'] ?? 0),
                uploadedBy: $userId,
                description: $data['description'] ?? null,
                registrationNumber: $data['registration_number'] ?? null,
                validity: $data['validity'] ?? null,
                complianceStatus: $data['compliance_status'] ?? null,
            );

            $taggableType = Str::of(class_basename(Document::class))->lower();
            $createTagAction->execute('farmer', (string) $farmer->id, $taggableType, (string) $document->id);

            if (! empty($data['document_type'])) {
                $createTagAction->execute('document', $data['document_type'], $taggableType, (string) $document->id);
            }
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Farmer $farmer)
    {
        $documents = Document::with(['tags', 'file', 'file.uploadedBy'])->whereHas('tags', function ($query) use ($farmer) {
            $query->where('key', 'farmer')
                ->where('value', $farmer->id);
        })->get();

        return Inertia::render('Farmers/Show', [
            'farmer' => array_merge($farmer->load([
                'address',
                'partner:id,name,code',
                'center:id,name',
                'projects:id,name,program',
            ])->toArray(), ['photo_url' => $farmer->photo_url, 'geotag_image_url' => $farmer->geotag_image_url]),
            'documents' => $documents,
            'enums' => [
                'document' => DocumentEnum::labels(),
                'complianceStatuses' => ComplianceStatusEnum::options(),
                'statuses' => ProfileStatusEnum::labels(),
                'addressRegion' => AddressRegionEnum::labels(),
                'addressDistrict' => AddressDistrictEnum::labels(),
                'farmerCategory' => FarmerCategoryEnum::labels(),
                'entrepreneurType' => EntrepreneurTypeEnum::labels(),
                'civilStatus' => CivilStatusEnum::labels(),
                'projectCategory' => $this->projectCategoryOptions($farmer->project_category),
                'educationalAttainment' => EducationalAttainmentEnum::labels(),
                'certificateStatus' => CertificateStatusEnum::labels(),
                'managementSystem' => ManagementSystemEnum::labels(),
                'feedingManagement' => FeedingManagementEnum::labels(),
                'incomeSource' => IncomeSourceEnum::labels(),
                'membershipType' => MembershipTypeEnum::labels(),
                'tvType' => TvTypeEnum::labels(),
                'farmBusinessRegistration' => FarmBusinessRegistrationEnum::labels(),
                'farmRegistrationStatus' => $this->farmRegistrationStatusOptions(),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Farmer $farmer)
    {
        $documents = Document::with(['tags', 'file', 'file.uploadedBy'])->whereHas('tags', function ($query) use ($farmer) {
            $query->where('key', 'farmer')
                ->where('value', $farmer->id);
        })->get();

        $farmer->load([
            'address',
            'partner:id,name,code',
            'center:id,name',
            'projects:id,name,program',
        ]);

        $tenant = resolve(TenantContext::class);

        $centers = Center::query()
            ->excludingHidden(Centers::hiddenCenters())
            ->when($tenant->agencyId(), fn ($q) => $q->where('agency_id', $tenant->agencyId()))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $partners = Partner::query()
            ->when($tenant->agencyId(), function ($q) use ($tenant) {
                $q->whereHas('center', fn ($cq) => $cq->where('agency_id', $tenant->agencyId()));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'center_id']);

        $projects = Project::query()->orderBy('program')->get(['id', 'program'])->mapWithKeys(function ($p) {
            $programValue = $p->program instanceof BackedEnum ? $p->program->value : $p->program;
            $label = ProjectProgramEnum::labels()[$programValue] ?? strtoupper((string) $programValue);

            return [$p->id => $label];
        })->toArray();

        return Inertia::render('Farmers/Edit', [
            'farmer' => array_merge($farmer->toArray(), ['photo_url' => $farmer->photo_url, 'geotag_image_url' => $farmer->geotag_image_url, 'entrepreneur_image_url' => $farmer->entrepreneur_image_url, 'enterprise_image_url' => $farmer->enterprise_image_url]),
            'documents' => $documents,
            'enums' => [
                'document' => DocumentEnum::labels(),
                'complianceStatuses' => ComplianceStatusEnum::options(),
                'statuses' => ProfileStatusEnum::labels(),
                'addressRegion' => AddressRegionEnum::labels(),
                'addressDistrict' => AddressDistrictEnum::labels(),
                'farmerCategory' => FarmerCategoryEnum::labels(),
                'entrepreneurType' => EntrepreneurTypeEnum::labels(),
                'civilStatus' => CivilStatusEnum::labels(),
                'projectCategory' => $this->projectCategoryOptions($farmer->project_category),
                'educationalAttainment' => EducationalAttainmentEnum::labels(),
                'certificateStatus' => CertificateStatusEnum::labels(),
                'managementSystem' => ManagementSystemEnum::labels(),
                'feedingManagement' => FeedingManagementEnum::labels(),
                'incomeSource' => IncomeSourceEnum::labels(),
                'membershipType' => MembershipTypeEnum::labels(),
                'tvType' => TvTypeEnum::labels(),
                'farmBusinessRegistration' => FarmBusinessRegistrationEnum::labels(),
                'farmRegistrationStatus' => $this->farmRegistrationStatusOptions(),
            ],
            'centers' => $centers,
            'partners' => $partners,
            'projects' => $projects,
            'defaultCenterId' => $farmer->center_id ? (int) $farmer->center_id : ($centers->first()?->id ? (int) $centers->first()->id : null),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFarmerRequest $request, Farmer $farmer)
    {
        $validated = $request->validated();

        $photoPath = $farmer->storePhoto($request->file('photo'));
        $geotagImagePath = $farmer->storeGeotagImage($request->file('geotag_image_file'));
        $entrepreneurImagePath = $farmer->storeEntrepreneurImage($request->file('entrepreneur_image_file'));
        $enterpriseImagePath = $farmer->storeEnterpriseImage($request->file('enterprise_image_file'));

        $addressData = [
            'street' => $request->has('street') ? $validated['street'] : null,
            'barangay' => $request->has('barangay') ? $validated['barangay'] : null,
            'city' => $request->has('municipality') ? $validated['municipality'] : null,
            'province' => $request->has('province') ? $validated['province'] : null,
            'region' => $request->has('region') ? $validated['region'] : null,
            'district' => $request->has('district') ? $validated['district'] : null,
            'latitude' => $request->has('gps_latitude') ? $validated['gps_latitude'] : null,
            'longitude' => $request->has('gps_longitude') ? $validated['gps_longitude'] : null,
        ];

        // The address lives on its own row, so nothing about it reaches the
        // farmer's own audit entry. It is read here, before the write, because
        // that is the only point where the pre-edit value still exists.
        $address = $farmer->address;

        $addressBefore = $address ? $address->only(array_keys($addressData)) : [];

        if ($address) {
            $address->update($addressData);
        } else {
            $address = $farmer->address()->create($addressData);
        }

        AuditTrail::logChanges(
            subject: $farmer,
            causer: $request->user(),
            description: 'Farmer Address Updated',
            before: $addressBefore,
            after: $address->only(array_keys($addressData)),
        );

        $dateOfBirth = $request->has('birthday') && ! empty($validated['birthday'])
            ? Date::parse($validated['birthday'])
            : $farmer->birthday;
        $age = $dateOfBirth?->age;

        $mergedAdditionalDetails = $farmer->metadata ?? [];
        if (! empty($validated['additional_details']) && is_array($validated['additional_details'])) {
            $mergedAdditionalDetails = array_merge($mergedAdditionalDetails, $validated['additional_details']);
        }

        $farmer->update([
            'center_id' => $request->has('center_id') ? (int) $validated['center_id'] : $farmer->center_id,
            'partner_id' => $request->has('partner_id') ? (($validated['partner_id'] ?? null) ? (int) $validated['partner_id'] : null) : $farmer->partner_id,
            'birthday' => $dateOfBirth,
            'photo' => $photoPath,
            'first_name' => $request->has('first_name') ? $validated['first_name'] : $farmer->first_name,
            'last_name' => $request->has('last_name') ? $validated['last_name'] : $farmer->last_name,
            'middle_name' => $request->has('middle_name') ? $validated['middle_name'] : $farmer->middle_name,
            'date_of_interview' => $request->has('date_of_interview') && ! empty($validated['date_of_interview'])
                ? Date::parse($validated['date_of_interview'])
                : ($farmer->date_of_interview ? Date::parse($farmer->date_of_interview) : null),
            'nickname' => $request->has('nickname') ? $validated['nickname'] : $farmer->nickname,
            'extension_name' => $request->has('extension_name') ? $validated['extension_name'] : $farmer->extension_name,
            'contact_number' => $request->has('contact_number') ? $validated['contact_number'] : $farmer->contact_number,
            'gender' => $request->has('gender') ? $validated['gender'] : $farmer->gender,
            'civil_status' => $request->has('civil_status') ? $validated['civil_status'] : $farmer->civil_status,
            'spouse_name' => $request->has('spouse_name') ? $validated['spouse_name'] : $farmer->spouse_name,
            'age' => $age,
            'household_size' => $request->has('household_size') ? $validated['household_size'] : $farmer->household_size,
            'position' => $request->has('position') ? $validated['position'] : $farmer->position,
            'year_involvement_in_business' => $request->has('year_involvement_in_business') ? $validated['year_involvement_in_business'] : $farmer->year_involvement_in_business,
            'family_member_attending_school_count' => $request->has('family_member_attending_school_count') ? $validated['family_member_attending_school_count'] : $farmer->family_member_attending_school_count,
            'female_count' => $request->has('female_count') ? $validated['female_count'] : $farmer->female_count,
            'male_count' => $request->has('male_count') ? $validated['male_count'] : $farmer->male_count,
            'is_pwd' => $request->has('is_pwd') ? $validated['is_pwd'] : $farmer->is_pwd,
            'is_4ps' => $request->has('is_4ps') ? $validated['is_4ps'] : $farmer->is_4ps,
            'is_ips' => $request->has('is_ips') ? $validated['is_ips'] : $farmer->is_ips,
            'is_indigent' => $request->has('is_indigent') ? $validated['is_indigent'] : $farmer->is_indigent,
            'indigenous_group' => $request->has('indigenous_group') ? $validated['indigenous_group'] : $farmer->indigenous_group,
            'educational_attainment' => $request->has('educational_attainment') ? $validated['educational_attainment'] : $farmer->educational_attainment,
            'ndalto_certification_status' => $request->has('ndalto_certification_status') ? $validated['ndalto_certification_status'] : $farmer->ndalto_certification_status,
            'ndalto_certification' => $request->has('ndalto_certification') ? $validated['ndalto_certification'] : $farmer->ndalto_certification,
            'type_of_entrepreneur' => $request->has('type_of_entrepreneur') ? $validated['type_of_entrepreneur'] : $farmer->type_of_entrepreneur,
            'rsbsa_cert_status' => $request->has('rsbsa_cert_status') ? $validated['rsbsa_cert_status'] : $farmer->rsbsa_cert_status,
            'rsbsa_certificate_of_registration' => $request->has('rsbsa_certificate_of_registration') ? $validated['rsbsa_certificate_of_registration'] : $farmer->rsbsa_certificate_of_registration,
            'if_producer_please_specify' => $request->has('if_producer_please_specify') ? $validated['if_producer_please_specify'] : $farmer->if_producer_please_specify,
            'involved_in_buffalo_farming' => $request->has('involved_in_buffalo_farming') ? $validated['involved_in_buffalo_farming'] : $farmer->involved_in_buffalo_farming,
            'how_many_in_family_are_involved_in_buffalo_farming' => $request->has('how_many_in_family_are_involved_in_buffalo_farming') ? $validated['how_many_in_family_are_involved_in_buffalo_farming'] : $farmer->how_many_in_family_are_involved_in_buffalo_farming,
            'are_you_the_head_of_the_family' => $request->has('are_you_the_head_of_the_family') ? $validated['are_you_the_head_of_the_family'] : $farmer->are_you_the_head_of_the_family,
            'if_no_highest_educational_attainment_of_head_of_family' => $request->has('if_no_highest_educational_attainment_of_head_of_family') ? $validated['if_no_highest_educational_attainment_of_head_of_family'] : $farmer->if_no_highest_educational_attainment_of_head_of_family,
            'challenges_or_problems_encountered_in_dairy_farming' => $request->has('challenges_or_problems_encountered_in_dairy_farming') ? $validated['challenges_or_problems_encountered_in_dairy_farming'] : $farmer->challenges_or_problems_encountered_in_dairy_farming,
            'main_source_of_income' => $request->has('main_source_of_income') ? $validated['main_source_of_income'] : $farmer->main_source_of_income,
            'main_annual_net_income' => $request->has('main_annual_net_income') ? $validated['main_annual_net_income'] : $farmer->main_annual_net_income,
            'secondary_source_of_income' => $request->has('secondary_source_of_income') ? $validated['secondary_source_of_income'] : $farmer->secondary_source_of_income,
            'secondary_annual_net_income' => $request->has('secondary_annual_net_income') ? $validated['secondary_annual_net_income'] : $farmer->secondary_annual_net_income,
            'tertiary_source_of_income' => $request->has('tertiary_source_of_income') ? $validated['tertiary_source_of_income'] : $farmer->tertiary_source_of_income,
            'tertiary_annual_net_income' => $request->has('tertiary_annual_net_income') ? $validated['tertiary_annual_net_income'] : $farmer->tertiary_annual_net_income,
            'quaternary_source_of_income' => $request->has('quaternary_source_of_income') ? $validated['quaternary_source_of_income'] : $farmer->quaternary_source_of_income,
            'quaternary_annual_net_income' => $request->has('quaternary_annual_net_income') ? $validated['quaternary_annual_net_income'] : $farmer->quaternary_annual_net_income,
            'avg_net_income' => $request->has('avg_net_income') ? $validated['avg_net_income'] : $farmer->avg_net_income,
            'total_net_income' => $request->has('total_net_income') ? $validated['total_net_income'] : $farmer->total_net_income,
            'staff_count_male' => $request->has('staff_count_male') ? $validated['staff_count_male'] : ($request->has('staff_count') ? $validated['staff_count'] : $farmer->staff_count_male ?? $farmer->staff_count),
            'staff_count_female' => $request->has('staff_count_female') ? $validated['staff_count_female'] : $farmer->staff_count_female,
            'ave_monthly_salary' => $request->has('ave_monthly_salary') ? $validated['ave_monthly_salary'] : $farmer->ave_monthly_salary,
            'status_google_map' => $request->has('status_google_map') ? $validated['status_google_map'] : $farmer->status_google_map,
            'google_map_link' => $request->has('google_map_link') ? $validated['google_map_link'] : $farmer->google_map_link,
            'gps_latitude' => $request->has('gps_latitude') ? $validated['gps_latitude'] : $farmer->gps_latitude,
            'gps_longitude' => $request->has('gps_longitude') ? $validated['gps_longitude'] : $farmer->gps_longitude,
            'pcc_assets_latitude' => $request->has('pcc_assets_latitude') ? $validated['pcc_assets_latitude'] : $farmer->pcc_assets_latitude,
            'pcc_assets_longitude' => $request->has('pcc_assets_longitude') ? $validated['pcc_assets_longitude'] : $farmer->pcc_assets_longitude,
            'geotag_image' => $geotagImagePath ?? ($request->has('geotag_image') ? $validated['geotag_image'] : $farmer->geotag_image),
            'entrepreneur_image' => $entrepreneurImagePath ?? ($request->has('entrepreneur_image') ? $validated['entrepreneur_image'] : $farmer->entrepreneur_image),
            'enterprise_image' => $enterpriseImagePath ?? ($request->has('enterprise_image') ? $validated['enterprise_image'] : $farmer->enterprise_image),
            'farm_name' => $request->has('farm_name') ? $validated['farm_name'] : $farmer->farm_name,
            'pcc_projects_assets' => $request->has('pcc_projects_assets') ? $validated['pcc_projects_assets'] : $farmer->pcc_projects_assets,
            'farm_region' => $request->has('farm_region') ? $validated['farm_region'] : $farmer->farm_region,
            'farm_province' => $request->has('farm_province') ? $validated['farm_province'] : $farmer->farm_province,
            'farm_municipality_city' => $request->has('farm_municipality_city') ? $validated['farm_municipality_city'] : $farmer->farm_municipality_city,
            'farm_barangay' => $request->has('farm_barangay') ? $validated['farm_barangay'] : $farmer->farm_barangay,
            'farm_purok_zone' => $request->has('farm_purok_zone') ? $validated['farm_purok_zone'] : $farmer->farm_purok_zone,
            'farm_district' => $request->has('farm_district') ? $validated['farm_district'] : $farmer->farm_district,
            'farm_street' => $request->has('farm_street') ? $validated['farm_street'] : $farmer->farm_street,
            'proposed_farm_name' => $request->has('proposed_farm_name') ? $validated['proposed_farm_name'] : $farmer->proposed_farm_name,
            'type_of_farm_business_registration' => $request->has('type_of_farm_business_registration') ? $validated['type_of_farm_business_registration'] : $farmer->type_of_farm_business_registration,
            'status_of_registration' => $request->has('status_of_registration') ? $validated['status_of_registration'] : $farmer->status_of_registration,
            'farm_registration_number' => $request->has('farm_registration_number') ? $validated['farm_registration_number'] : $farmer->farm_registration_number,
            'has_rsbsa' => $request->has('has_rsbsa') ? $validated['has_rsbsa'] : $farmer->has_rsbsa,
            'year_farm_established' => $request->has('year_farm_established') ? $validated['year_farm_established'] : $farmer->year_farm_established,
            'management_system' => $request->has('management_system') ? $validated['management_system'] : $farmer->management_system,
            'is_drylot' => $request->has('is_drylot') ? $validated['is_drylot'] : $farmer->is_drylot,
            'duration_of_stocking' => $request->has('duration_of_stocking') ? (int) $validated['duration_of_stocking'] : $farmer->duration_of_stocking,
            'is_applied_in_the_field' => $request->has('is_applied_in_the_field') ? $validated['is_applied_in_the_field'] : $farmer->is_applied_in_the_field,
            'is_dry_rice_field' => $request->has('is_dry_rice_field') ? $validated['is_dry_rice_field'] : $farmer->is_dry_rice_field,
            'is_dry_vegetable' => $request->has('is_dry_vegetable') ? $validated['is_dry_vegetable'] : $farmer->is_dry_vegetable,
            'is_vermicomposting' => $request->has('is_vermicomposting') ? $validated['is_vermicomposting'] : $farmer->is_vermicomposting,
            'is_pasture_range_paddock' => $request->has('is_pasture_range_paddock') ? $validated['is_pasture_range_paddock'] : $farmer->is_pasture_range_paddock,
            'is_daily_spread' => $request->has('is_daily_spread') ? $validated['is_daily_spread'] : $farmer->is_daily_spread,
            'is_storage_bulking' => $request->has('is_storage_bulking') ? $validated['is_storage_bulking'] : $farmer->is_storage_bulking,
            'is_liquid_slurry' => $request->has('is_liquid_slurry') ? $validated['is_liquid_slurry'] : $farmer->is_liquid_slurry,
            'is_anaerobic_digester' => $request->has('is_anaerobic_digester') ? $validated['is_anaerobic_digester'] : $farmer->is_anaerobic_digester,
            'is_solid_storage_covered' => $request->has('is_solid_storage_covered') ? $validated['is_solid_storage_covered'] : $farmer->is_solid_storage_covered,
            'is_solid_storage_additives' => $request->has('is_solid_storage_additives') ? $validated['is_solid_storage_additives'] : $farmer->is_solid_storage_additives,
            'is_uncovered_anaerobic_lagoon' => $request->has('is_uncovered_anaerobic_lagoon') ? $validated['is_uncovered_anaerobic_lagoon'] : $farmer->is_uncovered_anaerobic_lagoon,
            'is_twice_a_day_milking' => $request->has('is_twice_a_day_milking') ? $validated['is_twice_a_day_milking'] : $farmer->is_twice_a_day_milking,
            'daily_milk_production_min' => $request->has('daily_milk_production_min') ? $validated['daily_milk_production_min'] : $farmer->daily_milk_production_min,
            'daily_milk_production_max' => $request->has('daily_milk_production_max') ? $validated['daily_milk_production_max'] : $farmer->daily_milk_production_max,
            'milk_price_per_liter_min' => $request->has('milk_price_per_liter_min') ? $validated['milk_price_per_liter_min'] : $farmer->milk_price_per_liter_min,
            'milk_price_per_liter_max' => $request->has('milk_price_per_liter_max') ? $validated['milk_price_per_liter_max'] : $farmer->milk_price_per_liter_max,
            'milk_selling_locations' => $request->has('milk_selling_locations') ? $validated['milk_selling_locations'] : $farmer->milk_selling_locations,
            'milk_selling_restaurant_specify' => $request->has('milk_selling_restaurant_specify') ? $validated['milk_selling_restaurant_specify'] : $farmer->milk_selling_restaurant_specify,
            'milk_selling_processor_specify' => $request->has('milk_selling_processor_specify') ? $validated['milk_selling_processor_specify'] : $farmer->milk_selling_processor_specify,
            'milk_selling_collector_name' => $request->has('milk_selling_collector_name') ? $validated['milk_selling_collector_name'] : $farmer->milk_selling_collector_name,
            'milk_selling_others_specify' => $request->has('milk_selling_others_specify') ? $validated['milk_selling_others_specify'] : $farmer->milk_selling_others_specify,
            'assistance_needed' => $request->has('assistance_needed') ? $validated['assistance_needed'] : $farmer->assistance_needed,
            'forages_rainy_and_dry' => $farmer->forages_rainy_and_dry,
            'silage_specification' => $farmer->silage_specification,
            'dairy_cattle' => $request->has('dairy_cattle') ? $validated['dairy_cattle'] : $farmer->dairy_cattle,
            'beef_cattle' => $request->has('beef_cattle') ? $validated['beef_cattle'] : $farmer->beef_cattle,
            'goat' => $request->has('goat') ? $validated['goat'] : $farmer->goat,
            'chicken' => $request->has('chicken') ? $validated['chicken'] : $farmer->chicken,
            'duck' => $request->has('duck') ? $validated['duck'] : $farmer->duck,
            'geese' => $request->has('geese') ? $validated['geese'] : $farmer->geese,
            'swine' => $request->has('swine') ? $validated['swine'] : $farmer->swine,
            'horse' => $request->has('horse') ? $validated['horse'] : $farmer->horse,
            'other_livestock_details' => $request->has('other_livestock_details') ? $validated['other_livestock_details'] : $farmer->other_livestock_details,
            'source_of_income_details' => $request->has('source_of_income_details') ? $validated['source_of_income_details'] : $farmer->source_of_income_details,
            'animal_inventory' => $request->has('animal_inventory') ? $validated['animal_inventory'] : $farmer->animal_inventory,
            'animal_ownership' => $request->has('animal_ownership') ? $validated['animal_ownership'] : $farmer->animal_ownership,
            'feeding_management' => $request->has('feeding_management') ? $validated['feeding_management'] : $farmer->feeding_management,
            'membership_in_organization' => $request->has('membership_in_organization') ? $validated['membership_in_organization'] : $farmer->membership_in_organization,
            'name_of_organization' => $request->has('name_of_organization') ? $validated['name_of_organization'] : $farmer->name_of_organization,
            'tv_type' => $request->has('tv_type') ? $validated['tv_type'] : $farmer->tv_type,
            'description' => $farmer->description,
            'status' => $request->has('status') ? $validated['status'] : $farmer->status,
            'date_of_enrollment' => $request->has('date_of_enrollment') && ! empty($validated['date_of_enrollment'])
                ? Date::parse($validated['date_of_enrollment'])
                : ($farmer->date_of_enrollment ? Date::parse($farmer->date_of_enrollment) : null),
            'operation_in_charge' => $request->has('operation_in_charge') ? $validated['operation_in_charge'] : $farmer->operation_in_charge,
            'is_assisted' => $request->has('is_assisted') ? $validated['is_assisted'] : $farmer->is_assisted,
            'project_category' => $request->has('project_category') ? $validated['project_category'] : $farmer->project_category,
            'has_personal_cellphone' => $request->has('has_personal_cellphone') ? $validated['has_personal_cellphone'] : $farmer->has_personal_cellphone,
            'purok_zone' => $request->has('purok_zone') ? $validated['purok_zone'] : $farmer->purok_zone,
            'zip_code' => $request->has('zip_code') ? $validated['zip_code'] : $farmer->zip_code,
            'metadata' => $mergedAdditionalDetails === [] ? null : $mergedAdditionalDetails,
        ]);

        if ($request->has('project_ids')) {
            $this->syncProjects($request, $farmer, $validated['project_ids'] ?? []);
        }

        return to_route('farmers.edit', ['farmer' => $farmer->id])
            ->with('success', 'Farmer updated successfully!');
    }

    /**
     * Keep the farmer's project assignments in step with the form.
     *
     * The assignment is a pivot row rather than a column, so saving it writes no
     * attribute of its own and the farmer's own entry has nothing to report about
     * it. Without this a reviewer cannot tell that a farmer's project list changed
     * at all.
     *
     * @param  list<int|string>  $projectIds
     */
    private function syncProjects(Request $request, Farmer $farmer, array $projectIds): void
    {
        $before = $farmer->projects()->pluck('projects.name')->sort()->values()->all();

        $farmer->projects()->sync($projectIds);

        AuditTrail::logChanges(
            subject: $farmer,
            causer: $request->user(),
            description: 'Farmer Projects Updated',
            before: ['projects' => $before],
            after: ['projects' => $farmer->projects()->pluck('projects.name')->sort()->values()->all()],
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Farmer $farmer)
    {
        Farmer::destroy($farmer->getKey());

        // Back to the list the user came from, keeping its query string
        // (search, filters, sort, page). Redirecting to a bare farmers.index
        // dropped the search the user was looking at.
        return back(fallback: route('farmers.index'))
            ->with('success', 'Farmer deleted successfully.');
    }

    private function projectCategoryOptions(mixed $selected = null): array
    {
        $legacyOptions = ProjectProgramEnum::labels();

        $dynamicOptions = Project::query()
            ->whereNotNull('program')
            ->pluck('program')
            ->map(function (mixed $program): ?string {
                if ($program instanceof BackedEnum) {
                    return $program->value;
                }

                $program = is_string($program) ? trim($program) : null;

                return $program !== '' ? $program : null;
            })
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->mapWithKeys(fn (string $program): array => [$program => $legacyOptions[$program] ?? $program])
            ->all();

        $selectedValues = $selected instanceof BackedEnum
            ? [$selected->value]
            : (is_array($selected)
                ? array_map(fn ($item) => $item instanceof BackedEnum ? $item->value : (string) $item, $selected)
                : (is_string($selected) && $selected !== '' ? explode(',', $selected) : []));
        foreach ($selectedValues as $selectedValue) {
            $selectedValue = trim((string) $selectedValue);
            if ($selectedValue !== '' && ! isset($dynamicOptions[$selectedValue])) {
                $dynamicOptions[$selectedValue] = $legacyOptions[$selectedValue] ?? $selectedValue;
            }
        }

        return $dynamicOptions + $legacyOptions;
    }

    private function farmRegistrationStatusOptions(): array
    {
        return FarmRegistrationStatusEnum::labels();
    }
}
