<?php

namespace App\Http\Controllers;

use App\Actions\Document\CreateDocumentAction;
use App\Actions\Tag\CreateTagAction;
use App\Enums\AddressDistrictEnum;
use App\Enums\ComplianceStatusEnum;
use App\Enums\DairyBoxEnum;
use App\Enums\LandOwnershipEnum;
use App\Enums\PartnerCategoryEnum;
use App\Http\Requests\StoreDairyBoxRequest;
use App\Http\Requests\UpdateDairyBoxRequest;
use App\Models\Center;
use App\Models\DairyBox;
use App\Models\Document;
use App\Models\Partner;
use App\Models\Project;
use App\Sorts\RegionSort;
use App\Support\Audits\AuditTrail;
use App\Support\Centers;
use App\Support\Sanitizer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class DairyBoxController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $dairyBoxes = QueryBuilder::for(DairyBox::query()->select(['id', 'dairy_box_id', 'business_name', 'center_id', 'partner_id', 'status', 'launched_date', 'created_at'])
            ->when(
                $user && $user->isCenterScoped(),
                fn ($query) => $query->where('center_id', $user->center_id)
            )
            ->when(request()->get('search'), fn ($query, $search) => $query->globalSearch($search))
        )
            ->with(['center', 'partner', 'address'])
            ->allowedFilters([
                AllowedFilter::exact('center_id'),
                AllowedFilter::exact('status'),
            ])
            ->allowedSorts([
                'dairy_box_id',
                'business_name',
                AllowedSort::custom('region', new RegionSort),
                'launched_date',
                'created_at',
            ])
            ->defaultSort(AllowedSort::custom('region', new RegionSort))
            ->paginate(10)
            ->appends(request()->query());

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
            ->when(
                $user && $user->isCenterScoped(),
                fn ($query) => $query->where('center_id', $user->center_id)
            )
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'code', 'center_id']);

        $statuses = collect(DairyBoxEnum::cases())
            ->mapWithKeys(fn ($status) => [$status->value => DairyBoxEnum::labels()[$status->value]])
            ->toArray();

        $statusBadgeColors = DairyBoxEnum::getBadgeColors();

        return Inertia::render('DairyBoxes/Index', [
            'dairyBoxes' => $dairyBoxes,
            'centers' => $centers,
            'partners' => $partners,
            'statuses' => $statuses,
            'statusBadgeColors' => $statusBadgeColors,
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
                ?? Center::query()->when($tenant->agencyId(), fn ($q) => $q->where('agency_id', $tenant->agencyId()))->value('id');
            $disableCenterField = false;
        }

        if (! $centerId) {
            abort(403);
        }

        $centers = Center::query()
            ->excludingHidden(Centers::hiddenCenters())
            ->when($tenant->agencyId(), fn ($q) => $q->where('agency_id', $tenant->agencyId()))
            ->when(
                $user && $user->isCenterScoped(),
                fn ($q) => $q->where('id', $user->center_id)
            )
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $partners = Partner::query()
            ->when($tenant->agencyId(), function ($q) use ($tenant) {
                $q->whereHas('center', fn ($cq) => $cq->where('agency_id', $tenant->agencyId()));
            })
            ->when(
                $user && $user->isCenterScoped(),
                fn ($query) => $query->where('center_id', $user->center_id)
            )
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'code', 'center_id']);

        $statuses = collect(DairyBoxEnum::cases())
            ->mapWithKeys(fn ($status) => [$status->value => DairyBoxEnum::labels()[$status->value]])
            ->toArray();

        $statusBadgeColors = DairyBoxEnum::getBadgeColors();

        $projectInvolvements = $this->projectInvolvementOptions();

        return Inertia::render('DairyBoxes/Create', [
            'dairyBox' => array_merge((new DairyBox)->toArray(), ['logo_url' => null]),
            'centers' => $centers,
            'partners' => $partners,
            'projectInvolvements' => $projectInvolvements,
            'enums' => [
                'statuses' => $statuses,
                'statusBadgeColors' => $statusBadgeColors,
                'complianceStatus' => ComplianceStatusEnum::labels(),
                'partnerCategory' => PartnerCategoryEnum::labels(),
                'landOwnership' => LandOwnershipEnum::labels(),
                'addressDistrict' => AddressDistrictEnum::labels(),
                'addressDistrictMatches' => AddressDistrictEnum::matches(),
                'documentTypes' => $this->complianceDocumentTypes(),
            ],
            'center' => Center::find($centerId, ['id', 'name', 'code']),
            'defaultCenterId' => (int) $centerId,
            'disableCenterField' => $disableCenterField,
            'existingDocuments' => [],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(
        StoreDairyBoxRequest $request,
        CreateDocumentAction $createDocumentAction,
        CreateTagAction $createTagAction,
    ) {
        $user = $request->user();
        $validated = $request->validated();

        if ($user && $user->isCenterScoped()) {
            $validated['center_id'] = $user->center_id;
        }

        $dairyBox = DB::transaction(function () use (
            $request,
            $validated,
            $createDocumentAction,
            $createTagAction,
        ) {
            $logoPath = (new DairyBox)->storeLogo($request->file('logo'));
            $documents = $validated['documents'] ?? [];
            unset($validated['documents']);

            $dairyBox = DairyBox::create(array_merge($validated, [
                'logo' => $logoPath,
            ]));

            $addressData = [
                'street' => null,
                'barangay' => $validated['barangay'] ?? null,
                'city' => $validated['city'] ?? null,
                'province' => $validated['province'] ?? null,
                'region' => $validated['region'] ?? null,
                'district' => $validated['district'] ?? null,
                'zip_code' => $validated['zip_code'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
            ];

            $address = $dairyBox->address()->create($addressData);

            // The address is its own row, so nothing in the model's own entry
            // mentions the location the form just recorded.
            AuditTrail::logChanges(
                subject: $dairyBox,
                causer: $request->user(),
                description: 'Dairy Box Address Created',
                before: [],
                after: $address->only(array_keys($addressData)),
                event: 'created',
            );

            $this->createDocuments(
                $dairyBox,
                $documents,
                $request->user()->id,
                $createDocumentAction,
                $createTagAction,
            );

            return $dairyBox;
        });

        return to_route('dairy-boxes.edit', $dairyBox)
            ->with('success', 'Dairy box created successfully.');
    }

    /**
     * Create staged compliance documents and tag them to the new DairyBox.
     */
    private function createDocuments(
        DairyBox $dairyBox,
        array $documents,
        int $userId,
        CreateDocumentAction $createDocumentAction,
        CreateTagAction $createTagAction,
    ): void {
        $taggableType = Str::of(class_basename(Document::class))->lower();

        foreach ($documents as $data) {
            $document = $createDocumentAction->execute(
                title: $data['title'],
                fileName: $data['file_name'],
                fileKey: $data['file_key'],
                fileType: $data['file_type'] ?? 'application/octet-stream',
                fileSize: (int) ($data['file_size'] ?? 0),
                uploadedBy: $userId,
                description: $data['description'] ?? null,
                registrationNumber: $data['registration_number'] ?? null,
                validity: $data['validity'] ?? null,
                complianceStatus: $data['compliance_status'] ?? null,
            );

            $createTagAction->execute('dairy_box', (string) $dairyBox->id, $taggableType, (string) $document->id);
            $createTagAction->execute('document', $data['document_type'], $taggableType, (string) $document->id);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(DairyBox $dairyBox)
    {
        $dairyBox->load(['center', 'partner', 'address']);

        return Inertia::render('DairyBoxes/Show', [
            'dairyBox' => array_merge($dairyBox->toArray(), ['logo_url' => $dairyBox->logo_url]),
            'projectInvolvements' => $this->projectInvolvementOptions(),
            'enums' => [
                'statuses' => DairyBoxEnum::labels(),
                'complianceStatus' => ComplianceStatusEnum::labels(),
                'partnerCategory' => PartnerCategoryEnum::labels(),
                // The land ownership dropdown reads from the same list the request
                // validates against. Sharing the partner category list left 'Other'
                // and 'PCC' - values the records already hold - with no matching
                // option, so they were submitted unchanged and rejected on save.
                'landOwnership' => LandOwnershipEnum::labels(),
                'addressDistrict' => AddressDistrictEnum::labels(),
                'addressDistrictMatches' => AddressDistrictEnum::matches(),
                'documentTypes' => $this->complianceDocumentTypes(),
            ],
            'existingDocuments' => $this->loadDairyBoxDocuments($dairyBox),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, DairyBox $dairyBox)
    {
        $user = $request->user();
        $tenant = resolve(TenantContext::class);
        $centers = Center::query()
            ->excludingHidden(Centers::hiddenCenters())
            ->when($tenant->agencyId(), fn ($q) => $q->where('agency_id', $tenant->agencyId()))
            ->when(
                $user && $user->isCenterScoped(),
                fn ($q) => $q->where('id', $user->center_id)
            )
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $partners = Partner::query()
            ->when($tenant->agencyId(), function ($q) use ($tenant) {
                $q->whereHas('center', fn ($cq) => $cq->where('agency_id', $tenant->agencyId()));
            })
            ->when(
                $user && $user->isCenterScoped(),
                fn ($query) => $query->where('center_id', $user->center_id)
            )
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'code', 'center_id']);

        $statuses = collect(DairyBoxEnum::cases())
            ->mapWithKeys(fn ($status) => [$status->value => DairyBoxEnum::labels()[$status->value]])
            ->toArray();

        $statusBadgeColors = DairyBoxEnum::getBadgeColors();

        $dairyBox->load(['center', 'partner', 'address']);

        $projectInvolvements = $this->projectInvolvementOptions();

        return Inertia::render('DairyBoxes/Edit', [
            'dairyBox' => array_merge($dairyBox->toArray(), ['logo_url' => $dairyBox->logo_url]),
            'centers' => $centers,
            'partners' => $partners,
            'projectInvolvements' => $projectInvolvements,
            'enums' => [
                'statuses' => $statuses,
                'statusBadgeColors' => $statusBadgeColors,
                'complianceStatus' => ComplianceStatusEnum::labels(),
                'partnerCategory' => PartnerCategoryEnum::labels(),
                // The land ownership dropdown reads from the same list the request
                // validates against. Sharing the partner category list left 'Other'
                // and 'PCC' - values the records already hold - with no matching
                // option, so they were submitted unchanged and rejected on save.
                'landOwnership' => LandOwnershipEnum::labels(),
                'addressDistrict' => AddressDistrictEnum::labels(),
                'addressDistrictMatches' => AddressDistrictEnum::matches(),
                'documentTypes' => $this->complianceDocumentTypes(),
            ],
            'existingDocuments' => $this->loadDairyBoxDocuments($dairyBox),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDairyBoxRequest $request, DairyBox $dairyBox)
    {
        $validated = $request->validated();

        $logoPath = $dairyBox->storeLogo($request->file('logo'));

        $dairyBox->update(array_merge($validated, [
            'logo' => $logoPath,
        ]));

        $addressData = [
            'street' => null,
            'barangay' => $request->has('barangay') ? $validated['barangay'] : null,
            'city' => $request->has('city') ? $validated['city'] : null,
            'province' => $request->has('province') ? $validated['province'] : null,
            'region' => $request->has('region') ? $validated['region'] : null,
            'district' => $request->has('district') ? $validated['district'] : null,
            'zip_code' => $request->has('zip_code') ? $validated['zip_code'] : null,
            'latitude' => $request->has('latitude') ? $validated['latitude'] : null,
            'longitude' => $request->has('longitude') ? $validated['longitude'] : null,
        ];

        // Read before the write: that is the only point where the pre-edit address
        // still exists.
        $address = $dairyBox->address;
        $addressBefore = $address ? $address->only(array_keys($addressData)) : [];

        if ($address) {
            $address->update($addressData);
        } else {
            $address = $dairyBox->address()->create($addressData);
        }

        // An edit that moved only the address writes no dairy_boxes column, so the
        // model's own entry never appears and the change would go unrecorded.
        AuditTrail::logChanges(
            subject: $dairyBox,
            causer: $request->user(),
            description: 'Dairy Box Address Updated',
            before: $addressBefore,
            after: $address->only(array_keys($addressData)),
        );

        return back()->with('success', 'Dairy box updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DairyBox $dairyBox)
    {
        DairyBox::destroy($dairyBox->getKey());

        return to_route('dairy-boxes.index')
            ->with('success', 'Dairy box deleted successfully.');
    }

    /**
     * The 5 compliance document types shown as file-upload cards.
     */
    private function complianceDocumentTypes(): array
    {
        return [
            'bir' => 'BIR Registered',
            'fda-lto' => 'FDA LTO',
            'cso' => 'CSO Accreditation',
            'philgeps' => 'PhilGeps',
            'ffedis' => 'FFEDIS',
        ];
    }

    /**
     * Canonical, sorted list of the project involvement tags that fund source
     * fields are selected from.
     *
     * @return Collection<int, string>
     */
    private function projectInvolvementOptions(): Collection
    {
        // get() rather than pluck() so the model's `array` cast is applied;
        // pluck() would return raw JSON strings.
        $involvements = Project::query()
            ->whereNotNull('project_involvement')
            ->get('project_involvement')
            ->pluck('project_involvement')
            ->flatten();

        return collect(Sanitizer::parseProjectInvolvementList($involvements))->sort()->values();
    }

    /**
     * Load documents tagged to this DairyBox, indexed by document type.
     * Returns an array keyed by document type value.
     */
    private function loadDairyBoxDocuments(DairyBox $dairyBox): array
    {
        $docTypes = array_keys($this->complianceDocumentTypes());

        $documents = Document::with(['tags', 'file.uploadedBy'])
            ->whereHas('tags', fn ($q) => $q->where('key', 'dairy_box')->where('value', $dairyBox->id))
            ->whereHas('tags', fn ($q) => $q->where('key', 'document')->whereIn('value', $docTypes))
            ->get();

        $result = [];

        foreach ($documents as $doc) {
            $typeTag = $doc->tags->firstWhere('key', 'document');
            if (! $typeTag) {
                continue;
            }

            $result[$typeTag->value] = [
                'id' => $doc->id,
                'title' => $doc->title,
                'description' => $doc->description,
                'compliance_status' => $doc->compliance_status,
                'registration_number' => $doc->registration_number,
                'validity' => $doc->validity?->toDateString(),
                'document_type' => $typeTag->value,
                'file_path' => $doc->file?->path,
                'file_name' => $doc->file?->name,
                'file_type' => $doc->file?->type,
                'file_size' => $doc->file?->size,
                'uploaded_by' => $doc->file?->uploadedBy?->name,
            ];
        }

        return $result;
    }
}
