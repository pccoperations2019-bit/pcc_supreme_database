<?php

namespace App\Http\Controllers;

use App\Actions\Document\CreateDocumentAction;
use App\Actions\Tag\CreateTagAction;
use App\Enums\AddressDistrictEnum;
use App\Enums\AddressRegionEnum;
use App\Enums\ComplianceStatusEnum;
use App\Enums\ConsolidationCategoryEnum;
use App\Enums\DocumentEnum;
use App\Enums\HerdTypeEnum;
use App\Enums\HideProductTypeEnum;
use App\Enums\ManureProductTypeEnum;
use App\Enums\MeatProductTypeEnum;
use App\Enums\MilkProductTypeEnum;
use App\Enums\ProcessingCategoryEnum;
use App\Enums\ProductCategoryEnum;
use App\Enums\ProductionCategoryEnum;
use App\Enums\ProductSubCategoryEnum;
use App\Enums\ProfileStatusEnum;
use App\Enums\ProgramEnum;
use App\Enums\ProjectProgramEnum;
use App\Enums\UserRoleEnum;
use App\Http\Requests\StoreCenterRequest;
use App\Http\Requests\UpdateCenterRequest;
use App\Models\Center;
use App\Models\ConsolidationUnit;
use App\Models\Document;
use App\Models\MarketingUnit;
use App\Models\ProcessingUnit;
use App\Models\Product;
use App\Models\ProductionUnit;
use App\Models\Project;
use App\Queries\Center\ProductDocumentQuery;
use App\Services\UserProvisioningService;
use App\Sorts\RegionSort;
use App\Support\Audits\AuditTrail;
use App\Support\Centers;
use App\Workflows\ExportCenterPartnersWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class CenterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = request()->user();

        $this->authorize('viewAny', Center::class);

        return Inertia::render('Centers/Index', [
            'centers' => QueryBuilder::for(Center::class)
                // NHQ is hidden in every context. GP/NIZ stay visible here because
                // this index is not program-scoped, so the program-conditional
                // half of Centers::hiddenCenters() does not apply.
                ->excludingHidden(Centers::alwaysHiddenCenters())
                // A center-scoped account reads its own center only. Without this
                // the listing shipped every center in the agency to an encoder or
                // reviewer; the nav hides the link, but the route has to hold too.
                ->visibleTo($user)
                ->with([
                    'agency',
                    'address',
                ])
                ->withCount([
                    'productionUnits',
                    'processingUnits',
                    'consolidationUnits',
                    'marketingUnits',
                    'farmers',
                    'partners',
                ])
                ->allowedFilters([
                    AllowedFilter::callback('center_id', function ($query, $value) {
                        $raw = is_array($value) ? $value : explode(',', (string) $value);
                        $ids = collect($raw)
                            ->map(fn ($v) => (int) trim((string) $v))
                            ->filter(fn ($v) => $v > 0)
                            ->values()
                            ->all();

                        if ($ids !== []) {
                            $query->whereIn('id', $ids);
                        }
                    }),
                    AllowedFilter::callback('region', function ($query, $value) {
                        $query->whereHas('address', fn ($q) => $q->where('region', $value));
                    }),
                    AllowedFilter::exact('status'),
                ])
                ->allowedSorts([
                    'code',
                    'name',
                    AllowedSort::custom('region', new RegionSort),
                    'created_at',
                ])
                ->defaultSort(AllowedSort::custom('region', new RegionSort))
                ->get(),
            'filters' => [
                'clusteredCenters' => Centers::clusterLabels(),
            ],
            'enums' => [
                'center' => Centers::labelsByCode(),
            ],
            'can' => [
                // Allow-list: creating a center is a PCC-level action, so it is
                // gated on the permission rather than on "is not a center admin",
                // which every new center-scoped role would otherwise inherit.
                'create_center' => $user?->can('manage-center') ?? false,
            ],
            'user_center_id' => $user ? $user->center_id : null,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Centers/Create', [
            'center' => array_merge((new Center)->toArray(), ['logo_url' => null]),
            'products' => [],
            'documents' => [],
            'projects' => Project::query()->orderBy('program')->get(['id', 'program'])->mapWithKeys(function ($p) {
                $programValue = $p->program instanceof ProjectProgramEnum ? $p->program->value : (string) $p->program;
                $label = ProjectProgramEnum::labels()[$programValue] ?? strtoupper($programValue);

                return [$p->id => $label];
            })->toArray(),
            'enums' => [
                'document' => DocumentEnum::labels(),
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
                'center' => Centers::labelsByCode(),
                'statuses' => ProfileStatusEnum::labels(),
                'programs' => ProgramEnum::labels(),
                'projectPrograms' => ProjectProgramEnum::labels(),
                'complianceStatuses' => ComplianceStatusEnum::options(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(
        StoreCenterRequest $request,
        CreateDocumentAction $createDocumentAction,
        CreateTagAction $createTagAction,
    ) {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validated();

        $center = DB::transaction(function () use (
            $request,
            $tenant,
            $validated,
            $createDocumentAction,
            $createTagAction,
        ) {
            $center = new Center;
            $logoPath = $center->storeLogo($request->file('logo'));

            $metadata = [
                'date_of_establishment' => $validated['date_of_establishment'] ?? null,
                'position' => $validated['position'] ?? null,
                'telephone_number' => $validated['telephone_number'] ?? null,
            ];

            $center->code = $validated['code'];
            $center->agency_id = (int) $tenant->agencyId();
            $center->name = $validated['name'];
            $center->status = $validated['status'] ?? ProfileStatusEnum::DEFAULT->value;
            $center->program = $validated['program'] ?? ProgramEnum::DEFAULT->value;
            $center->description = null;
            $center->website = $validated['website'] ?? null;
            $center->logo = $logoPath;
            $center->contact_person = $validated['contact_person'] ?? null;
            $center->contact_number = $validated['contact_number'] ?? null;
            $center->email_address = $validated['email_address'] ?? null;
            $center->metadata = $metadata;
            $center->animal_inventory = $validated['animal_inventory'] ?? null;
            $center->date_assisted = $validated['date_assisted'] ?? null;
            $center->save();

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

            $center->address()->create($addressData);

            $this->createUnits($center, $validated);
            $this->createProducts($center, $validated, $request->user()->id, $createDocumentAction, $createTagAction);
            $this->createDocuments(
                $center,
                $validated,
                $request->user()->id,
                $createDocumentAction,
                $createTagAction,
            );

            if (! empty($validated['project_ids'])) {
                $center->projects()->sync($validated['project_ids']);
            }

            $this->createCenterUser($center, $validated);

            return $center;
        });

        return to_route('centers.edit', $center)
            ->with('success', 'Center created successfully.');
    }

    /**
     * Create the login account for a newly created center.
     *
     * Goes through UserProvisioningService so the account gets the same scope
     * checks, program handling and status bookkeeping as one created from the
     * user management screens, instead of building the row inline and
     * bypassing the approval gate.
     */
    private function createCenterUser(Center $center, array $validated): void
    {
        app(UserProvisioningService::class)->create(
            name: $center->name.' Administrator',
            email: $validated['email_address'],
            username: $validated['username'],
            password: $validated['password'],
            role: UserRoleEnum::CENTER_ADMIN,
            center: $center,
        );
    }

    /**
     * Create value chain units from a one-time create payload.
     */
    private function createUnits(Center $center, array $validated): void
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
                $unit->center()->associate($center);

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
        Center $center,
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
                    'product_registration' => $data['product_registration'] ?? null,
                    'size' => $data['size'] ?? null,
                    'cost_per_qty' => isset($data['cost_per_qty']) ? (float) $data['cost_per_qty'] : 0.00,
                    'details' => $data['details'] ?? null,
                ]
            );

            $product->centers()->syncWithoutDetaching([$center->id]);

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
                $createTagAction->execute('center', $center->code, $taggableType, (string) $document->id);
                $createTagAction->execute('product', $product->code, $taggableType, (string) $document->id);
                $createTagAction->execute('document', 'product_registration', $taggableType, (string) $document->id);
            }
        }
    }

    /**
     * Create documents from a one-time create payload, tagged to the new center.
     */
    private function createDocuments(
        Center $center,
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
            $createTagAction->execute('center', $center->code, $taggableType, (string) $document->id);

            if (! empty($data['document_type'])) {
                $createTagAction->execute('document', $data['document_type'], $taggableType, (string) $document->id);
            }
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Center $center, ProductDocumentQuery $productDocumentQuery)
    {
        $this->authorize('view', $center);

        $user = request()->user();

        $documents = Document::whereHas('tags', function ($query) use ($center) {
            $query->where('key', 'center')
                ->where('value', $center->code);
        })->whereDoesntHave('tags', function ($query) {
            $query->where('key', 'document')
                ->where('value', 'product_registration');
        })->with(['tags', 'file', 'file.uploadedBy'])->get();

        $partners = $center->partners()
            ->with(['address'])
            ->paginate(10);

        return Inertia::render('Centers/Show', [
            'center' => array_merge($center->load([
                'agency',
                'address',
                'productionUnits',
                'processingUnits',
                'consolidationUnits',
                'marketingUnits',
                'projects',
            ])->toArray(), ['logo_url' => $center->logo_url]),
            'documents' => $documents,
            'products' => $center->products()->get(),
            'productRegistrationDocuments' => $productDocumentQuery->productRegistrationDocumentsByCode($center),
            'partners' => $partners,
            'projects' => Project::query()->orderBy('program')->get(['id', 'program'])->mapWithKeys(function ($p) {
                $programValue = $p->program instanceof ProjectProgramEnum ? $p->program->value : (string) $p->program;
                $label = ProjectProgramEnum::labels()[$programValue] ?? strtoupper($programValue);

                return [$p->id => $label];
            })->toArray(),
            'enums' => [
                'document' => DocumentEnum::labels(),
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
                'center' => Centers::labelsByCode(),
                'statuses' => ProfileStatusEnum::labels(),
                'programs' => ProgramEnum::labels(),
                'projectPrograms' => ProjectProgramEnum::labels(),
                'complianceStatuses' => ComplianceStatusEnum::options(),
            ],
            'user_center_id' => $user ? $user->center_id : null,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Center $center, ProductDocumentQuery $productDocumentQuery)
    {
        $this->authorize('update', $center);

        $documents = Document::with(['tags', 'file', 'file.uploadedBy'])->whereHas('tags', function ($query) use ($center) {
            $query->where('key', 'center')
                ->where('value', $center->code);
        })->whereDoesntHave('tags', function ($query) {
            $query->where('key', 'document')
                ->where('value', 'product_registration');
        })->get();

        $center->load([
            'agency',
            'address',
            'productionUnits',
            'processingUnits',
            'consolidationUnits',
            'marketingUnits',
            'projects',
        ]);

        $partners = $center->partners()
            ->with(['address'])
            ->paginate(10);

        return Inertia::render('Centers/Edit', [
            'center' => array_merge($center->toArray(), ['logo_url' => $center->logo_url]),
            'documents' => $documents,
            'products' => $center->products()->get(),
            'productRegistrationDocuments' => $productDocumentQuery->productRegistrationDocumentsByCode($center),
            'partners' => $partners,
            'projects' => Project::query()->orderBy('program')->get(['id', 'program'])->mapWithKeys(function ($p) {
                $programValue = $p->program instanceof ProjectProgramEnum ? $p->program->value : (string) $p->program;
                $label = ProjectProgramEnum::labels()[$programValue] ?? strtoupper($programValue);

                return [$p->id => $label];
            })->toArray(),
            'enums' => [
                'document' => DocumentEnum::labels(),
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
                'center' => Centers::labelsByCode(),
                'statuses' => ProfileStatusEnum::labels(),
                'programs' => ProgramEnum::labels(),
                'projectPrograms' => ProjectProgramEnum::labels(),
                'complianceStatuses' => ComplianceStatusEnum::options(),
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCenterRequest $request, Center $center)
    {
        $this->authorize('update', $center);

        $validated = $request->validated();

        $logoPath = $center->storeLogo($request->file('logo'));

        $metadata = array_merge($center->metadata ?? [], [
            'date_of_establishment' => $request->has('date_of_establishment') ? $validated['date_of_establishment'] : ($center->metadata['date_of_establishment'] ?? null),
            'position' => $request->has('position') ? $validated['position'] : ($center->metadata['position'] ?? null),
            'telephone_number' => $request->has('telephone_number') ? $validated['telephone_number'] : ($center->metadata['telephone_number'] ?? null),
        ]);

        // A center that has never had any of these filled in would otherwise be
        // handed a bag of nulls, which is a written change: every save reports
        // "Metadata: null -> nothing" and buries the edits that matter.
        if (array_filter($metadata, fn ($value) => $value !== null) === []) {
            $metadata = null;
        }

        // The address lives on its own row, so nothing about it reaches the center's
        // own audit entry. It is read here, before the write, because that is the
        // only point where the pre-edit value still exists.
        $address = $center->address;

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
            $address = $center->address()->create($addressData);
        }

        AuditTrail::logChanges(
            subject: $center,
            causer: $request->user(),
            description: 'Center Address Updated',
            before: $addressBefore,
            after: $address->only(array_keys($addressData)),
        );

        $center->name = $request->has('name') ? $validated['name'] : $center->name;
        $center->status = $request->has('status') ? $validated['status'] : $center->status;
        $center->program = $request->has('program') ? $validated['program'] : $center->program;
        $center->website = $request->has('website') ? $validated['website'] : $center->website;
        $center->logo = $logoPath;
        $center->contact_person = $request->has('contact_person') ? $validated['contact_person'] : $center->contact_person;
        $center->contact_number = $request->has('contact_number') ? $validated['contact_number'] : $center->contact_number;
        $center->email_address = $request->has('email_address') ? $validated['email_address'] : $center->email_address;
        $center->metadata = $metadata;
        $center->animal_inventory = $request->has('animal_inventory') ? $validated['animal_inventory'] : $center->animal_inventory;
        if ($request->has('date_assisted')) {
            $center->date_assisted = $validated['date_assisted'];
        }
        $center->save();

        if ($request->has('project_ids')) {
            $this->syncProjects($request, $center, $validated['project_ids'] ?? []);
        }

        return back()->with('success', 'Center updated successfully.');
    }

    /**
     * Keep the center's project assignments in step with the form.
     *
     * The assignment is a pivot row rather than a column, so saving it writes no
     * attribute of its own and the center's own entry has nothing to report about
     * it. Without this a reviewer cannot tell that a center's project list changed
     * at all.
     *
     * @param  list<int|string>  $projectIds
     */
    private function syncProjects(Request $request, Center $center, array $projectIds): void
    {
        $before = $center->projects()->pluck('projects.name')->sort()->values()->all();

        $center->projects()->sync($projectIds);

        AuditTrail::logChanges(
            subject: $center,
            causer: $request->user(),
            description: 'Center Projects Updated',
            before: ['projects' => $before],
            after: ['projects' => $center->projects()->pluck('projects.name')->sort()->values()->all()],
        );
    }

    /**
     * Export partners for a center
     */
    public function exportPartners(
        Request $request,
        Center $center,
        CreateDocumentAction $createDocumentAction,
        CreateTagAction $createTagAction,
    ) {
        // An encoder may export, but only its own center — the scope check
        // below enforces that, and the permission check keeps the endpoint
        // closed to roles that hold neither export permission.
        abort_unless($request->user()?->canExportSomething(), 403, 'You are not allowed to export data.');

        // A center-scoped user may only ever export their own center, whatever
        // center id the request names.
        abort_if(
            $request->user()->isCenterScoped() && $request->user()->center_id !== $center->id,
            403,
            'You may only export your own center.'
        );

        $validated = $request->validate([
            'format' => ['required', 'string', 'in:xlsx,csv,pdf'],
            'queue_export' => ['nullable', 'boolean'],
        ]);

        $format = $validated['format'];
        $queueExport = (bool) ($validated['queue_export'] ?? true);

        $workflow = new ExportCenterPartnersWorkflow;

        if ($queueExport) {
            $document = $workflow->queueExport(
                center: $center,
                format: $format,
                createDocumentAction: $createDocumentAction,
                createTagAction: $createTagAction,
            );

            return response()->json([
                'message' => 'Export queued successfully.',
                'document_id' => $document->id,
            ], 202);
        }

        return $workflow->handleDirectExport($center, $format);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Center $center)
    {
        $this->authorize('delete', $center);

        Center::destroy($center->getKey());

        return to_route('centers.index')
            ->with('success', 'Center deleted successfully.');
    }
}
