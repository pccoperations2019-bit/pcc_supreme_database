<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ProjectCategoryEnum;
use App\Http\Requests\StoreProjectSiteRequest;
use App\Http\Requests\UpdateProjectSiteRequest;
use App\Models\ProjectSite;
use App\Services\PartnerCenterValidator;
use App\Services\ProjectSiteAddressService;
use App\Services\ProjectSiteFormDataService;
use App\Services\ProjectSiteRelationsService;
use App\Support\Audits\ProjectSiteAudit;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CCDPProjectSiteController extends Controller
{
    private const PROJECT = ProjectCategoryEnum::CCDP->value;

    public function __construct(
        private readonly ProjectSiteFormDataService $formData,
        private readonly ProjectSiteAddressService $addresses,
        private readonly ProjectSiteRelationsService $relations,
        private readonly ProjectSiteAudit $audit,
        private readonly PartnerCenterValidator $partnerCenterValidator,
    ) {}

    public function index(): Response
    {
        $projectSites = QueryBuilder::for(ProjectSite::class)
            ->visibleTo(request()->user())
            ->select(['id', 'project_site_code', 'center_id', 'production_partner_id', 'project_id', 'year', 'inauguration_date', 'created_at'])
            ->allowedFilters([
                AllowedFilter::partial('project_site_code'),
                AllowedFilter::exact('center_id'),
                AllowedFilter::exact('production_partner_id'),
                AllowedFilter::exact('project_id'),
            ])
            ->allowedSorts(['project_site_code', 'year', 'inauguration_date', 'created_at'])
            ->defaultSort('-created_at')
            ->whereHas('project', fn ($query) => $query->where('program', self::PROJECT))
            ->with(['center', 'partner', 'project', 'serviceRecord'])
            ->when(request()->get('search'), function ($query, $search) {
                $query->whereHas('partner', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            })
            ->paginate(10)
            ->appends(request()->query());

        return Inertia::render('ProjectSites/CCDP/Index', [
            'projectSites' => $projectSites,
            ...$this->formData->sharedProps(self::PROJECT),
        ]);
    }

    public function create(): Response
    {
        $this->abortIfCannotEnterData();

        return Inertia::render('ProjectSites/CCDP/Create', [
            'projectSite' => new ProjectSite,
            'program' => self::PROJECT,
            ...$this->formData->sharedProps(self::PROJECT, scopeProjectsToProgram: true),
        ]);
    }

    public function store(StoreProjectSiteRequest $request): RedirectResponse
    {
        $this->abortIfCannotEnterData();

        $validated = $request->validated();

        $centerId = $this->toId($validated['center_id'] ?? null);
        $partnerId = $this->toId($validated['production_partner_id'] ?? null);

        if (! $this->partnerCenterValidator->belongsToCenter($partnerId, $centerId)) {
            return $this->partnerMismatchResponse();
        }

        $processingPartnerId = $this->toId($validated['processing_partner_id'] ?? null);

        if (! $this->partnerCenterValidator->belongsToCenter($processingPartnerId, $centerId)) {
            return $this->processingPartnerMismatchResponse();
        }

        $addressFields = $this->addresses->extract($validated);
        $siteData = $this->addresses->withoutAddress($validated);
        unset($siteData['partner_farmer_org']);
        $metadata = array_key_exists('partner_farmer_org', $validated)
            ? ['partner_farmer_org' => $validated['partner_farmer_org']]
            : [];

        $projectSiteCode = empty($validated['project_site_code'])
            ? ProjectSite::generateCode(self::PROJECT, isset($validated['year']) ? (int) $validated['year'] : null)
            : $validated['project_site_code'];

        DB::transaction(function () use ($siteData, $centerId, $partnerId, $processingPartnerId, $validated, $addressFields, $request, $projectSiteCode, $metadata): void {
            $projectSite = ProjectSite::create([
                ...$siteData,
                'metadata' => $metadata,
                'project_site_code' => $projectSiteCode,
                'center_id' => $centerId,
                'production_partner_id' => $partnerId,
                'processing_partner_id' => $processingPartnerId,
                'project_id' => $this->toId($validated['project_id'] ?? null),
            ]);

            $this->addresses->create($projectSite, $addressFields);
            $this->relations->sync($projectSite, $request);

            // The site's own columns are logged by the model. Its address and the
            // nine record groups the form carries alongside them are separate rows,
            // so without this a creation is only half of what the submission wrote.
            $this->audit->record(
                site: $projectSite,
                before: [],
                description: 'CCDP Project Site Related Records Created',
                causer: $request->user(),
                event: 'created',
            );
        });

        return to_route('ccdp-project-sites.index')
            ->with('success', 'CCDP project site created successfully.');
    }

    public function show(ProjectSite $projectSite): Response
    {
        $this->authorize('view', $projectSite);

        $projectSite->load($this->editRelations());

        $this->mapSingleRelations($projectSite);

        return Inertia::render('ProjectSites/CCDP/Show', [
            'projectSite' => $projectSite,
            ...$this->formData->sharedProps(self::PROJECT),
        ]);
    }

    public function edit(ProjectSite $projectSite): Response
    {
        $this->authorize('update', $projectSite);

        $projectSite->load($this->editRelations());

        $this->mapSingleRelations($projectSite);

        return Inertia::render('ProjectSites/CCDP/Edit', [
            'projectSite' => $projectSite,
            ...$this->formData->sharedProps(self::PROJECT),
        ]);
    }

    private function editRelations(): array
    {
        return [
            'center', 'partner', 'processingPartner', 'project', 'serviceRecord', 'address',
            'infrastructure.address',
            'infrastructure.processingStaff',
            'marketingInfrastructure.address',
            'marketingInfrastructure.dairyBox.partner',
            'marketingInfrastructure.marketingStaff',
            'productions',
            'equipment',
            'trainingRecords',
            'documents',
            'budgetUtilization',
        ];
    }

    private function mapSingleRelations(ProjectSite $projectSite): void
    {
        $budgetUtilization = $projectSite->getRelation('budgetUtilization');
        $projectSite->setRelation('budgetUtilization', $budgetUtilization?->first());

        $projectSite->setAttribute(
            'legal_documents',
            $projectSite->getRelation('documents')->reduce(function ($carry, $doc) {
                $carry[$doc->type] = $doc->description;

                return $carry;
            }, [])
        );

        $projectSite->setAttribute(
            'legal_document_urls',
            $projectSite->getRelation('documents')->reduce(function ($carry, $doc) {
                $carry[$doc->type] = $doc->attachment
                    ? Storage::disk('media')->url($doc->attachment)
                    : null;

                return $carry;
            }, [])
        );

        $this->flattenAddresses($projectSite->getRelation('infrastructure'));
        $this->flattenAddresses($projectSite->getRelation('marketingInfrastructure'));
        $this->mapMarketingInfrastructureDetails($projectSite->getRelation('marketingInfrastructure'));
        $this->setNestedStaffAliases($projectSite->getRelation('infrastructure'), 'processingStaff', 'processing_staff');
        $this->setNestedStaffAliases($projectSite->getRelation('marketingInfrastructure'), 'marketingStaff', 'marketing_staff');
        $projectSite->setAttribute('processing_staff', $projectSite->getRelation('infrastructure')->flatMap(fn ($item) => $item->processingStaff)->values());
        $projectSite->setAttribute('marketing_staff', $projectSite->getRelation('marketingInfrastructure')->flatMap(fn ($item) => $item->marketingStaff)->values());
        $projectSite->setAttribute('processing_infrastructure', $projectSite->getRelation('infrastructure'));
        $projectSite->setAttribute('marketing_infrastructure', $projectSite->getRelation('marketingInfrastructure'));
        $projectSite->setAttribute('partner_farmer_org', $projectSite->metadata['partner_farmer_org'] ?? null);
    }

    private function flattenAddresses(EloquentCollection $items): void
    {
        $fields = ProjectSiteAddressService::FIELDS;
        $items->each(function ($item) use ($fields) {
            if (! $item->address) {
                return;
            }
            foreach ($fields as $field) {
                $item->setAttribute($field, $item->address->{$field} ?? null);
            }
        });
    }

    private function mapMarketingInfrastructureDetails($items): void
    {
        $items->each(function ($item): void {
            $dairyBox = $item->dairyBox;

            $item->setAttribute('partner', $item->partner ?: $dairyBox?->partner?->name);
            $item->setAttribute('business_name', $item->business_name ?: $dairyBox?->business_name);
            $item->setAttribute('manager', $item->manager ?: $dairyBox?->manager);
            $item->setAttribute('operational_status', $item->operational_status ?: $dairyBox?->status);
            $item->setAttribute('launched_date', $item->launched_date ?: $dairyBox?->launched_date);
        });
    }

    private function setNestedStaffAliases(EloquentCollection $items, string $relation, string $attribute): void
    {
        $items->each(function ($item) use ($relation, $attribute): void {
            $item->setAttribute($attribute, $item->getRelation($relation));
        });
    }

    public function update(UpdateProjectSiteRequest $request, ProjectSite $projectSite): RedirectResponse
    {
        $this->authorize('update', $projectSite);

        $validated = $request->validated();

        $centerId = array_key_exists('center_id', $validated) ? $this->toId($validated['center_id']) : null;
        $partnerId = array_key_exists('production_partner_id', $validated) ? $this->toId($validated['production_partner_id']) : null;

        if (! $this->partnerCenterValidator->belongsToCenter($partnerId, $centerId)) {
            return $this->partnerMismatchResponse();
        }

        $processingPartnerId = array_key_exists('processing_partner_id', $validated)
            ? $this->toId($validated['processing_partner_id'])
            : null;

        if (! $this->partnerCenterValidator->belongsToCenter($processingPartnerId, $centerId)) {
            return $this->processingPartnerMismatchResponse();
        }

        $addressFields = $this->addresses->extract($validated);
        $siteData = $this->addresses->withoutAddress($validated);
        unset($siteData['partner_farmer_org']);

        // The record groups are replaced on write, so what was there has to be read
        // before the save - afterwards there is no way back to it.
        $before = $this->audit->capture($projectSite);

        DB::transaction(function () use ($projectSite, $siteData, $centerId, $partnerId, $processingPartnerId, $validated, $addressFields, $request, $before): void {
            $metadata = $projectSite->metadata ?? [];

            if (array_key_exists('partner_farmer_org', $validated)) {
                $metadata['partner_farmer_org'] = $validated['partner_farmer_org'];
            }

            $projectSite->update([
                ...$siteData,
                'metadata' => $metadata,
                'center_id' => $centerId,
                'production_partner_id' => $partnerId,
                'processing_partner_id' => $processingPartnerId,
                'project_id' => array_key_exists('project_id', $validated) ? $this->toId($validated['project_id']) : null,
            ]);

            $this->addresses->sync($projectSite, $addressFields);
            $this->relations->sync($projectSite, $request);

            // An edit that touches only the address or a record group moves no site
            // column, so the model's own entry never appears. Without this, the
            // productions a reviewer just changed leave no trace at all.
            $this->audit->record(
                site: $projectSite,
                before: $before,
                description: 'CCDP Project Site Related Records Updated',
                causer: $request->user(),
            );
        });

        return to_route('ccdp-project-sites.index')
            ->with('success', 'CCDP project site updated successfully.');
    }

    public function destroy(ProjectSite $projectSite): RedirectResponse
    {
        $this->authorize('delete', $projectSite);

        $projectSite->delete();

        return to_route('ccdp-project-sites.index')
            ->with('success', 'CCDP project site deleted successfully.');
    }

    private function toId(mixed $value): ?int
    {
        return empty($value) ? null : (int) $value;
    }

    private function partnerMismatchResponse(): RedirectResponse
    {
        return back()->withErrors([
            'production_partner_id' => 'Selected partner does not belong to the selected center.',
        ])->withInput();
    }

    private function processingPartnerMismatchResponse(): RedirectResponse
    {
        return back()->withErrors([
            'processing_partner_id' => 'Selected processing infrastructure partner does not belong to the selected center.',
        ])->withInput();
    }

    /**
     * Filing a project site is data entry, so it is gated on 'enter-data' rather
     * than on being a center-level account. ProjectSitePolicy::create() allows a
     * center to file its own site; aborting every center-level account here
     * contradicted that policy and blocked the encoder outright.
     */
    private function abortIfCannotEnterData(): void
    {
        abort_unless(request()->user()?->can('enter-data'), 403);
    }
}
