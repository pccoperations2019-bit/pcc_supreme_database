<?php

namespace App\Http\Controllers;

use App\Actions\Document\CreateDocumentAction;
use App\Actions\Tag\CreateTagAction;
use App\Enums\ActiveInactiveEnum;
use App\Enums\AddressDistrictEnum;
use App\Enums\AddressRegionEnum;
use App\Enums\ComplianceStatusEnum;
use App\Enums\ConsolidationCategoryEnum;
use App\Enums\DairyBoxEnum;
use App\Enums\DocumentEnum;
use App\Enums\HerdTypeEnum;
use App\Enums\HideProductTypeEnum;
use App\Enums\ManureProductTypeEnum;
use App\Enums\MeatProductTypeEnum;
use App\Enums\MilkProductTypeEnum;
use App\Enums\PartnerCategoryEnum;
use App\Enums\PartnerPartnershipTypeEnum;
use App\Enums\PartnerTypeEnum;
use App\Enums\ProcessingCategoryEnum;
use App\Enums\ProductCategoryEnum;
use App\Enums\ProductionCategoryEnum;
use App\Enums\ProductSubCategoryEnum;
use App\Enums\ProfileStatusEnum;
use App\Enums\ProgramEnum;
use App\Enums\ProjectProgramEnum;
use App\Enums\RegistrationTypeEnum;
use App\Http\Requests\StorePartnerRequest;
use App\Http\Requests\UpdatePartnerRequest;
use App\Models\Center;
use App\Models\ConsolidationUnit;
use App\Models\Document;
use App\Models\MarketingUnit;
use App\Models\Partner;
use App\Models\ProcessingUnit;
use App\Models\Product;
use App\Models\ProductionUnit;
use App\Models\Project;
use App\Queries\Center\ProductDocumentQuery;
use App\Sorts\RegionSort;
use App\Support\Audits\AuditTrail;
use App\Support\Centers;
use App\Support\Tenancy\TenantContext;
use App\Workflows\ExportPartnerMembersWorkflow;
use Illuminate\Http\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class PartnerController extends Controller
{
    /**
     * Keys persisted in the partners.metadata JSON column via the form.
     */
    private const METADATA_KEYS = [
        'position',
        'telephone_number',
        'date_of_enrollment',
        'incharge',
        'business_name',
        'business_shortname',
        'assistance_status',
        'date_established',
        'date_assisted',
        'partner_businesstype',
        'partner_category',
        'registrationtype',
        'registration_no',
        'registration_date',
        'purokzone',
        'name_of_manager',
        'manager_contact_no',
        'manager_email',
        'contact_person_contact_no',
        'contact_person_email',
        'bodcount',
        'bodschedule',
        'active_count',
        'main_business',
        'other_line_of_business',
        'businesscount',
        'status_google_map',
        'geo_tag_image',
        'google_map_link',
        'ndaltocertificationstatus',
        'ndaltocertno',
        'ndaltocertno_validity',
        'fdaltocertificationstatus',
        'fdaltocertno',
        'fdaltocertno_validity',
        'philgepsstatus',
        'philgepsstatus_validity',
        'csocertificationstatus',
        'cso_validity',
        'ffedisstatus',
        'ffedis_certno',
        'assets',
        'asset_php',
        'asset_year',
        'netincome_php',
        'income_year',
        'function_in_the_value_chain',
        'production',
        'processing',
        'marketing',
        'engagedmilkfeeding',
    ];

    /**
     * Display a listing of the resource.
     */
    public function index(HttpRequest $request)
    {
        $user = $request->user();

        $partners = QueryBuilder::for(
            Partner::query()
                ->select(['id', 'code', 'name', 'center_id', 'category', 'status', 'partnership_type', 'created_at'])
                ->when(
                    $user && $user->isCenterScoped(),
                    fn ($query) => $query->where('center_id', $user->center_id)
                )
                ->with([
                    'address',
                    'center:id,name,code',
                    'project:id,program',
                    'projects:id,program',
                ])
                ->withCount([
                    'productionUnits',
                    'consolidationUnits',
                    'processingUnits',
                    'marketingUnits',
                ])
        )
            ->when(request()->get('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->allowedFilters([
                AllowedFilter::callback('key', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%")
                            ->orWhere('code', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::partial('name'),
                AllowedFilter::exact('center_id'),
                AllowedFilter::exact('category'),
                AllowedFilter::exact('status'),
            ])
            ->allowedSorts([
                'code',
                'name',
                'status',
                'category',
                AllowedSort::custom('region', new RegionSort),
                'created_at',
            ])
            ->defaultSort(AllowedSort::custom('region', new RegionSort))
            ->paginate(10)
            ->appends(request()->query());

        return Inertia::render('Partners/Index', [
            'partners' => $partners,
            'enums' => [
                'categories' => PartnerCategoryEnum::labels(),
                'statuses' => ProfileStatusEnum::labels(),
                'partnershipTypes' => PartnerPartnershipTypeEnum::labels(),
                'projectPrograms' => ProjectProgramEnum::labels(),
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
            $centerId = $tenant->selectedCenterId()
                ?? Center::query()->where('agency_id', $tenant->agencyId())->value('id');
            $disableCenterField = false;
        }

        if (! $centerId) {
            abort(403);
        }

        $centers = Center::query()
            ->excludingHidden(Centers::hiddenCenters())
            ->when($tenant->agencyId(), fn ($q) => $q->where('agency_id', $tenant->agencyId()))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return Inertia::render('Partners/Create', [
            'partner' => array_merge((new Partner)->toArray(), ['logo_url' => null]),
            'products' => [],
            'documents' => [],
            'enums' => [
                'addressRegion' => AddressRegionEnum::labels(),
                'addressDistrict' => AddressDistrictEnum::labels(),
                'categories' => PartnerCategoryEnum::labels(),
                'statuses' => ProfileStatusEnum::labels(),
                'partnershipTypes' => PartnerPartnershipTypeEnum::labels(),
                'document' => DocumentEnum::labels(),
                'complianceStatuses' => ComplianceStatusEnum::options(),
                'assistanceStatuses' => ActiveInactiveEnum::labels(),
                'registrationTypes' => RegistrationTypeEnum::labels(),
                'partnerTypes' => PartnerTypeEnum::labels(),
                'productionCategory' => ProductionCategoryEnum::labels(),
                'herdType' => HerdTypeEnum::labels(),
                'consolidationCategory' => ConsolidationCategoryEnum::labels(),
                'processingCategory' => ProcessingCategoryEnum::labels(),
                'productCategory' => ProductCategoryEnum::labels(),
                'productSubCategory' => ProductSubCategoryEnum::labels(),
                'milkProductType' => MilkProductTypeEnum::labels(),
                'meatProductType' => MeatProductTypeEnum::labels(),
                'meatProductTypeCategories' => MeatProductTypeEnum::categories(),
                'manureProductType' => ManureProductTypeEnum::labels(),
                'hideProductType' => HideProductTypeEnum::labels(),
                'programs' => ProgramEnum::labels(),
                'projectPrograms' => ProjectProgramEnum::labels(),
            ],
            'center' => Center::find($centerId, ['id', 'name', 'code']),
            'centers' => $centers,
            'projects' => Project::query()->orderBy('program')->pluck('program', 'id')->toArray(),
            'defaultCenterId' => (int) $centerId,
            'disableCenterField' => $disableCenterField,
        ]);
    }

    /**
     * Search partners for AJAX requests.
     */
    public function search(Request $request)
    {
        $user = $request->user();
        $key = $request->query('key', '');

        $partners = Partner::query()
            ->when(
                $user && $user->isCenterScoped(),
                fn ($query) => $query->where('center_id', $user->center_id)
            )
            ->when($key, function ($query, $key) {
                $query->where('name', 'like', "%{$key}%");
            })
            ->limit(20)
            ->get()
            ->map(function ($partner) {
                return [
                    'value' => $partner->id,
                    'label' => $partner->name,
                ];
            });

        return response()->json($partners);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(
        StorePartnerRequest $request,
        CreateDocumentAction $createDocumentAction,
        CreateTagAction $createTagAction,
    ) {
        $tenant = resolve(TenantContext::class);
        $validated = $request->validated();

        $centerId = (int) ($validated['center_id'] ?? 0);
        if ($centerId === 0) {
            abort(422);
        }

        $center = Center::query()
            ->when($tenant->agencyId(), fn ($q) => $q->where('agency_id', $tenant->agencyId()))
            ->find($centerId);
        if (! $center) {
            abort(403);
        }

        $code = $validated['code'];

        $logoPath = (new Partner)->storeLogo($request->file('logo'));

        $metadata = [];
        foreach (self::METADATA_KEYS as $key) {
            if (array_key_exists($key, $validated)) {
                $metadata[$key] = $validated[$key];
            }
        }

        $partner = DB::transaction(function () use (
            $center,
            $code,
            $validated,
            $logoPath,
            $metadata,
            $request,
            $createDocumentAction,
            $createTagAction,
        ) {
            $partner = Partner::create([
                'code' => $code,
                'center_id' => (int) $center->id,
                'name' => $validated['name'],
                'category' => $validated['category'] ?? null,
                'partnership_type' => $validated['partnership_type'] ?? null,
                'program' => $validated['program'] ?? ProgramEnum::DEFAULT->value,
                'website' => $validated['website'] ?? null,
                'logo' => $logoPath,
                'contact_name' => $validated['contact_person'] ?? null,
                'position' => $validated['position'] ?? null,
                'contact_email' => $validated['email_address'] ?? null,
                'mobile_number' => $validated['contact_number'] ?? null,
                'telephone_number' => $validated['telephone_number'] ?? null,
                'status' => $validated['status'] ?? ProfileStatusEnum::DEFAULT->value,
                'total_composition' => (int) ($validated['total_composition'] ?? 0),
                'male_count' => (int) ($validated['male_count'] ?? 0),
                'female_count' => (int) ($validated['female_count'] ?? 0),
                'avg_monthly_income' => (float) ($validated['avg_monthly_income'] ?? 0),
                'animal_inventory' => $validated['animal_inventory'] ?? null,
                'metadata' => $metadata,
            ]);

            $addressData = [
                'street' => $validated['street'] ?? null,
                'barangay' => $validated['barangay'] ?? null,
                'city' => $validated['city'] ?? null,
                'province' => $validated['province'] ?? null,
                'region' => $validated['region'] ?? null,
                'district' => $validated['district'] ?? null,
                'zip_code' => $validated['zip_code'] ?? null,
                'country' => $validated['country'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
            ];

            $partner->address()->create($addressData);

            if (! empty($validated['project_ids'])) {
                $partner->projects()->sync($validated['project_ids']);
            }

            $this->createUnits($partner, $validated);
            $this->createProducts(
                $partner,
                $validated,
                $request->user()->id,
                $createDocumentAction,
                $createTagAction,
            );
            $this->createDocuments(
                $partner,
                $validated,
                $request->user()->id,
                $createDocumentAction,
                $createTagAction,
            );

            return $partner;
        });

        return to_route('partners.edit', $partner)
            ->with('success', 'Partner created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Partner $partner, ProductDocumentQuery $productDocumentQuery)
    {
        $documents = Document::with(['tags', 'file', 'file.uploadedBy'])->whereHas('tags', function ($query) use ($partner) {
            $query->where('key', 'partner')
                ->where('value', $partner->id);
        })->get();

        $farmers = $partner->farmers()
            ->with(['address'])
            ->paginate(10);

        $partner->metadata = $this->normalizeMetadata($partner->metadata);

        return Inertia::render('Partners/Show', [
            'partner' => array_merge($partner->load([
                'address',
                'productionUnits',
                'processingUnits',
                'consolidationUnits',
                'marketingUnits',
                'dairyBoxes',
                'projects',
            ])->toArray(), ['logo_url' => $partner->logo_url]),
            'documents' => $documents,
            'products' => $partner->products()->get(),
            'productRegistrationDocuments' => $productDocumentQuery->productRegistrationDocumentsByCode($partner),
            'farmers' => $farmers,
            'enums' => [
                'document' => DocumentEnum::labels(),
                'complianceStatuses' => ComplianceStatusEnum::options(),
                'productionCategory' => ProductionCategoryEnum::labels(),
                'herdType' => HerdTypeEnum::labels(),
                'consolidationCategory' => ConsolidationCategoryEnum::labels(),
                'processingCategory' => ProcessingCategoryEnum::labels(),
                'productCategory' => ProductCategoryEnum::labels(),
                'productSubCategory' => ProductSubCategoryEnum::labels(),
                'milkProductType' => MilkProductTypeEnum::labels(),
                'meatProductType' => MeatProductTypeEnum::labels(),
                'meatProductTypeCategories' => MeatProductTypeEnum::categories(),
                'manureProductType' => ManureProductTypeEnum::labels(),
                'hideProductType' => HideProductTypeEnum::labels(),
                'addressRegion' => AddressRegionEnum::labels(),
                'addressDistrict' => AddressDistrictEnum::labels(),
                'partnershipTypes' => PartnerPartnershipTypeEnum::labels(),
                'categories' => PartnerCategoryEnum::labels(),
                'statuses' => ProfileStatusEnum::labels(),
                'dairyBoxStatuses' => DairyBoxEnum::labels(),
                'assistanceStatuses' => ActiveInactiveEnum::labels(),
                'registrationTypes' => RegistrationTypeEnum::labels(),
                'partnerTypes' => PartnerTypeEnum::labels(),
                'programs' => ProgramEnum::labels(),
                'projectPrograms' => ProjectProgramEnum::labels(),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Partner $partner, ProductDocumentQuery $productDocumentQuery)
    {
        $documents = Document::with(['tags', 'file', 'file.uploadedBy'])->whereHas('tags', function ($query) use ($partner) {
            $query->where('key', 'partner')
                ->where('value', $partner->id);
        })->get();

        $partner->load([
            'address',
            'productionUnits',
            'processingUnits',
            'consolidationUnits',
            'marketingUnits',
            'dairyBoxes',
            'projects',
        ]);

        $tenant = resolve(TenantContext::class);

        $centers = Center::query()
            ->excludingHidden(Centers::hiddenCenters())
            ->when($tenant->agencyId(), fn ($q) => $q->where('agency_id', $tenant->agencyId()))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $farmers = $partner->farmers()
            ->with(['address'])
            ->paginate(10);

        $partner->metadata = $this->normalizeMetadata($partner->metadata);

        return Inertia::render('Partners/Edit', [
            'partner' => array_merge($partner->toArray(), ['logo_url' => $partner->logo_url]),
            'documents' => $documents,
            'products' => $partner->products()->get(),
            'productRegistrationDocuments' => $productDocumentQuery->productRegistrationDocumentsByCode($partner),
            'farmers' => $farmers,
            'enums' => [
                'document' => DocumentEnum::labels(),
                'complianceStatuses' => ComplianceStatusEnum::options(),
                'productionCategory' => ProductionCategoryEnum::labels(),
                'herdType' => HerdTypeEnum::labels(),
                'consolidationCategory' => ConsolidationCategoryEnum::labels(),
                'processingCategory' => ProcessingCategoryEnum::labels(),
                'productCategory' => ProductCategoryEnum::labels(),
                'productSubCategory' => ProductSubCategoryEnum::labels(),
                'milkProductType' => MilkProductTypeEnum::labels(),
                'meatProductType' => MeatProductTypeEnum::labels(),
                'meatProductTypeCategories' => MeatProductTypeEnum::categories(),
                'manureProductType' => ManureProductTypeEnum::labels(),
                'hideProductType' => HideProductTypeEnum::labels(),
                'addressRegion' => AddressRegionEnum::labels(),
                'addressDistrict' => AddressDistrictEnum::labels(),
                'partnershipTypes' => PartnerPartnershipTypeEnum::labels(),
                'categories' => PartnerCategoryEnum::labels(),
                'statuses' => ProfileStatusEnum::labels(),
                'dairyBoxStatuses' => DairyBoxEnum::labels(),
                'assistanceStatuses' => ActiveInactiveEnum::labels(),
                'registrationTypes' => RegistrationTypeEnum::labels(),
                'partnerTypes' => PartnerTypeEnum::labels(),
                'programs' => ProgramEnum::labels(),
                'projectPrograms' => ProjectProgramEnum::labels(),
            ],
            'centers' => $centers,
            'projects' => Project::query()->orderBy('program')->get(['id', 'program'])->mapWithKeys(function ($p) {
                $programValue = $p->program instanceof \BackedEnum ? $p->program->value : $p->program;
                $label = ProjectProgramEnum::labels()[$programValue] ?? strtoupper((string) $programValue);

                return [$p->id => $label];
            })->toArray(),
        ]);
    }

    /**
     * Map raw stored metadata values onto canonical enum values so existing
     * records (with mixed casing or label text) match the dropdown options.
     */
    private function normalizeMetadata(?array $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        $metadata['registrationtype'] = RegistrationTypeEnum::normalize($metadata['registrationtype'] ?? null);
        $metadata['partner_businesstype'] = PartnerCategoryEnum::normalize($metadata['partner_businesstype'] ?? null);
        $metadata['assistance_status'] = ActiveInactiveEnum::normalize($metadata['assistance_status'] ?? null);
        $metadata['partner_category'] = PartnerCategoryEnum::normalize($metadata['partner_category'] ?? null);

        return $metadata;
    }

    /**
     * Create value chain units from a one-time create payload.
     */
    private function createUnits(Partner $partner, array $validated): void
    {
        $unitTypes = [
            'production_units' => ProductionUnit::class,
            'consolidation_units' => ConsolidationUnit::class,
            'processing_units' => ProcessingUnit::class,
            'marketing_units' => MarketingUnit::class,
        ];

        foreach ($unitTypes as $key => $modelClass) {
            foreach ($validated[$key] ?? [] as $data) {
                $unit = new $modelClass;
                $unit->partner()->associate($partner);
                $unit->center()->associate($partner->center);

                if ($modelClass === ProductionUnit::class) {
                    $data['category'] = ($data['category'] ?? null) ?: ProductionCategoryEnum::DEFAULT;
                    $data['herd_type'] = ($data['herd_type'] ?? null) ?: HerdTypeEnum::DEFAULT;
                    $data['number_of_staff_male'] = (int) ($data['number_of_staff_male'] ?? 0);
                    $data['number_of_staff_female'] = (int) ($data['number_of_staff_female'] ?? 0);
                    $data['avg_salary_of_staff'] = (float) ($data['avg_salary_of_staff'] ?? 0);
                }

                $unit->fill($data)->save();
            }
        }
    }

    /**
     * Create products from a one-time create payload, with optional registration documents.
     */
    private function createProducts(
        Partner $partner,
        array $validated,
        int $userId,
        CreateDocumentAction $createDocumentAction,
        CreateTagAction $createTagAction,
    ): void {
        foreach ($validated['products'] ?? [] as $data) {
            $category = ProductCategoryEnum::from($data['category']);
            $subCategory = isset($data['sub_category']) && $data['sub_category'] !== ''
                ? ProductSubCategoryEnum::from($data['sub_category'])
                : null;

            $code = $data['code'] ?? null;
            if ($code === null || $code === '') {
                $code = Product::generateCode($category->value, $subCategory?->value);
            }

            $product = Product::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $data['name'],
                    'category' => $category,
                    'sub_category' => $subCategory,
                    'product_type' => $data['product_type'] ?? null,
                    'product_registration' => $data['product_registration'] ?? null,
                    'size' => $data['size'] ?? null,
                    'cost_per_qty' => isset($data['cost_per_qty']) ? (float) $data['cost_per_qty'] : 0.00,
                    'details' => $data['details'] ?? null,
                ]
            );

            $product->partners()->syncWithoutDetaching([$partner->id]);

            if (! empty($data['registration_file_key'])) {
                $document = $createDocumentAction->execute(
                    title: $data['registration_file_name'] ?? 'Product Registration',
                    fileName: $data['registration_file_name'],
                    fileKey: $data['registration_file_key'],
                    fileType: $data['registration_file_type'] ?? null,
                    fileSize: (int) ($data['registration_file_size'] ?? 0),
                    uploadedBy: $userId,
                    description: 'Product Registration PDF',
                    registrationNumber: $data['product_registration'] ?? null,
                );

                $taggableType = Str::of(class_basename(Document::class))->lower();
                $createTagAction->execute('partner', $partner->code, $taggableType, (string) $document->id);
                $createTagAction->execute('product', $product->code, $taggableType, (string) $document->id);
                $createTagAction->execute('document', 'product_registration', $taggableType, (string) $document->id);
            }
        }
    }

    /**
     * Create documents from a one-time create payload, tagged to the new partner.
     */
    private function createDocuments(
        Partner $partner,
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
            $createTagAction->execute('partner', (string) $partner->id, $taggableType, (string) $document->id);

            if (! empty($data['document_type'])) {
                $createTagAction->execute('document', $data['document_type'], $taggableType, (string) $document->id);
            }
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePartnerRequest $request, Partner $partner)
    {
        $validated = $request->validated();

        $logoPath = $partner->storeLogo($request->file('logo'));

        $metadata = array_merge($partner->metadata ?? [], array_intersect_key($validated, array_flip(self::METADATA_KEYS)));

        $partner->update([
            'center_id' => $request->has('center_id') ? (int) $validated['center_id'] : $partner->center_id,
            'code' => $request->has('code') ? $validated['code'] : $partner->code,
            'name' => $request->has('name') ? $validated['name'] : $partner->name,
            'category' => $request->has('category') ? $validated['category'] : $partner->category,
            'partnership_type' => $request->has('partnership_type') ? $validated['partnership_type'] : $partner->partnership_type,
            'program' => $request->has('program') ? $validated['program'] : $partner->program,
            'website' => $request->has('website') ? $validated['website'] : $partner->website,
            'logo' => $logoPath,
            'contact_name' => $request->has('contact_person') ? $validated['contact_person'] : $partner->contact_name,
            'position' => $request->has('position') ? $validated['position'] : $partner->position,
            'contact_email' => $request->has('email_address') ? $validated['email_address'] : $partner->contact_email,
            'mobile_number' => $request->has('contact_number') ? $validated['contact_number'] : $partner->mobile_number,
            'telephone_number' => $request->has('telephone_number') ? $validated['telephone_number'] : $partner->telephone_number,
            'status' => $request->has('status') ? $validated['status'] : $partner->status,
            'total_composition' => $request->has('total_composition') ? (int) $validated['total_composition'] : $partner->total_composition,
            'male_count' => $request->has('male_count') ? (int) $validated['male_count'] : $partner->male_count,
            'female_count' => $request->has('female_count') ? (int) $validated['female_count'] : $partner->female_count,
            'avg_monthly_income' => $request->has('avg_monthly_income') ? (float) $validated['avg_monthly_income'] : $partner->avg_monthly_income,
            'animal_inventory' => $request->has('animal_inventory') ? $validated['animal_inventory'] : $partner->animal_inventory,
            'metadata' => $metadata,
        ]);

        // The address lives on its own row, so nothing about it reaches the
        // partner's own audit entry. It is read here, before the write, because
        // that is the only point where the pre-edit value still exists.
        $address = $partner->address;

        $addressData = [
            'street' => $request->has('street') ? $validated['street'] : null,
            'barangay' => $request->has('barangay') ? $validated['barangay'] : null,
            'city' => $request->has('city') ? $validated['city'] : null,
            'province' => $request->has('province') ? $validated['province'] : null,
            'region' => $request->has('region') ? $validated['region'] : null,
            'district' => $request->has('district') ? $validated['district'] : null,
            'zip_code' => $request->has('zip_code') ? $validated['zip_code'] : null,
            'country' => $request->has('country') ? $validated['country'] : null,
            'latitude' => $request->has('latitude') ? $validated['latitude'] : null,
            'longitude' => $request->has('longitude') ? $validated['longitude'] : null,
        ];

        $addressBefore = $address ? $address->only(array_keys($addressData)) : [];

        if ($address) {
            $address->update($addressData);
        } else {
            $address = $partner->address()->create($addressData);
        }

        AuditTrail::logChanges(
            subject: $partner,
            causer: $request->user(),
            description: 'Partner Address Updated',
            before: $addressBefore,
            after: $address->only(array_keys($addressData)),
        );

        if ($request->has('project_ids')) {
            $this->syncProjects($request, $partner, $validated['project_ids'] ?? []);
        }

        return back()->with('success', 'Partner updated successfully.');
    }

    /**
     * Keep the partner's project assignments in step with the form.
     *
     * The assignment is a pivot row rather than a column, so saving it writes no
     * attribute of its own and the partner's own entry has nothing to report about
     * it. Without this a reviewer cannot tell that a partner's project list changed
     * at all.
     *
     * @param  list<int|string>  $projectIds
     */
    private function syncProjects(Request $request, Partner $partner, array $projectIds): void
    {
        $before = $partner->projects()->pluck('projects.name')->sort()->values()->all();

        $partner->projects()->sync($projectIds);

        AuditTrail::logChanges(
            subject: $partner,
            causer: $request->user(),
            description: 'Partner Projects Updated',
            before: ['projects' => $before],
            after: ['projects' => $partner->projects()->pluck('projects.name')->sort()->values()->all()],
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Partner $partner)
    {
        // Set partner_id to null in related records before deleting
        $partner->milkProductions()->update(['partner_id' => null]);
        $partner->milkSales()->update(['partner_id' => null]);
        $partner->milkTradeds()->update(['partner_id' => null]);
        $partner->milkConsolidations()->update(['partner_id' => null]);
        $partner->milkFeedingPrograms()->update(['partner_id' => null]);

        Partner::destroy($partner->getKey());

        // Back to the list the user came from, keeping its query string
        // (search, filters, sort, page). Aligns with FarmerController@destroy
        // which already preserves the search the user was looking at.
        return back(fallback: route('partners.index'))
            ->with('success', 'Partner deleted successfully.');
    }

    /**
     * Export members for a specific partner.
     */
    public function exportMembers(
        Request $request,
        Partner $partner,
        CreateDocumentAction $createDocumentAction,
        CreateTagAction $createTagAction,
    ) {
        // An encoder may export, but only partners in its own center — the
        // scope check below enforces that.
        abort_unless($request->user()?->canExportSomething(), 403, 'You are not allowed to export data.');

        // A center-scoped user may only ever export partners in their own
        // center, whatever partner id the request names.
        abort_if(
            $request->user()->isCenterScoped() && $request->user()->center_id !== $partner->center?->id,
            403,
            'You may only export partners from your own center.'
        );

        $validated = $request->validate([
            'format' => ['required', 'string', 'in:xlsx,csv,pdf'],
            'queue_export' => ['nullable', 'boolean'],
        ]);

        $format = $validated['format'];
        $queueExport = (bool) ($validated['queue_export'] ?? true);

        $workflow = new ExportPartnerMembersWorkflow;

        if ($queueExport) {
            $document = $workflow->queueExport(
                partner: $partner,
                format: $format,
                createDocumentAction: $createDocumentAction,
                createTagAction: $createTagAction,
            );

            return response()->json([
                'message' => 'Export queued successfully.',
                'document_id' => $document->id,
            ], 202);
        }

        return $workflow->handleDirectExport($partner, $format);
    }
}
