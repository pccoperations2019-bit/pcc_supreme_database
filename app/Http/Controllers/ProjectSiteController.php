<?php

namespace App\Http\Controllers;

use App\Enums\ProjectProgramEnum;
use App\Http\Requests\StoreProjectSiteRequest;
use App\Http\Requests\UpdateProjectSiteRequest;
use App\Models\Center;
use App\Models\DairyBox;
use App\Models\Partner;
use App\Models\Project;
use App\Models\ProjectSite;
use App\Services\ProjectSiteAddressService;
use App\Services\ProjectSiteFormDataService;
use App\Services\ProjectSiteRelationsService;
use App\Support\Centers;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProjectSiteController extends Controller
{
    public function __construct(
        private readonly ProjectSiteAddressService $addresses,
        private readonly ProjectSiteRelationsService $relations,
        private readonly ProjectSiteFormDataService $formData,
    ) {}

    public function index()
    {
        $projectSites = QueryBuilder::for(ProjectSite::class)
            ->allowedFilters([
                AllowedFilter::partial('project_site_code'),
                AllowedFilter::exact('center_id'),
                AllowedFilter::exact('production_partner_id'),
                AllowedFilter::exact('project_id'),
            ])
            ->allowedSorts(['project_site_code', 'inauguration_date', 'created_at'])
            ->defaultSort('-created_at')
            ->when(request('program'), function ($query, $program) {
                $query->whereHas('project', fn ($q) => $q->where('program', $program));
            })
            ->with([
                'center',
                'partner',
                'project',
                'serviceRecord',
            ])
            ->paginate(10)
            ->appends(request()->query());

        $program = request('program');
        $programLabel = $program
            ? ProjectProgramEnum::tryFrom($program)?->label()
            : null;

        return Inertia::render('ProjectSites/Index', [
            'projectSites' => $projectSites,
            'program' => $program,
            'programLabel' => $programLabel,
            'centers' => Center::query()->excludingHidden(Centers::hiddenCenters())->orderBy('name', 'asc')->get(['id', 'name', 'code']),
            'partners' => Partner::query()
                ->with('address')
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'code', 'center_id']),
            'projects' => Project::orderBy('name', 'asc')->get(['id', 'name', 'program', 'project_involvement']),
            'dairyBoxes' => DairyBox::query()
                ->with('address')
                ->orderBy('dairy_box_id', 'asc')
                ->get(['id', 'partner_id', 'dairy_box_id', 'business_name', 'manager', 'status', 'launched_date']),
            'enums' => $this->formData->enums(),
        ]);
    }

    public function create()
    {
        return Inertia::render('ProjectSites/Create', [
            'projectSite' => new ProjectSite,
            'program' => request('program'),
            'centers' => Center::query()->excludingHidden(Centers::hiddenCenters())->orderBy('name', 'asc')->get(['id', 'name', 'code']),
            'partners' => Partner::query()
                ->with('address')
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'code', 'center_id']),
            'projects' => Project::orderBy('name', 'asc')->get(['id', 'name', 'program', 'project_involvement']),
            'dairyBoxes' => DairyBox::query()
                ->with('address')
                ->orderBy('dairy_box_id', 'asc')
                ->get(['id', 'partner_id', 'dairy_box_id', 'business_name', 'manager', 'status', 'launched_date']),
            'enums' => $this->formData->enums(),
        ]);
    }

    public function store(StoreProjectSiteRequest $request)
    {
        $validated = $request->validated();

        $centerId = empty($validated['center_id']) ? null : (int) $validated['center_id'];
        $partnerId = empty($validated['production_partner_id']) ? null : (int) $validated['production_partner_id'];

        if ($centerId && $partnerId) {
            $partner = Partner::query()->select(['id', 'center_id'])->find($partnerId);

            if (! $partner || (int) $partner->center_id !== $centerId) {
                return back()->withErrors([
                    'production_partner_id' => 'Selected partner does not belong to the selected center.',
                ])->withInput();
            }
        }

        $addressFields = $this->addresses->extract($validated);
        $siteData = $this->addresses->withoutAddress($validated);

        $projectId = empty($validated['project_id']) ? null : (int) $validated['project_id'];
        $program = ($projectId ? Project::find($projectId)?->program : null) ?? ProjectProgramEnum::DEFAULT;

        $projectSiteCode = empty($validated['project_site_code'])
            ? ProjectSite::generateCode($program->value, isset($validated['year']) ? (int) $validated['year'] : null)
            : $validated['project_site_code'];

        $projectSite = ProjectSite::create([
            ...$siteData,
            'project_site_code' => $projectSiteCode,
            'center_id' => $centerId,
            'production_partner_id' => $partnerId,
            'project_id' => $projectId,
        ]);

        $this->addresses->create($projectSite, $addressFields);
        $this->relations->sync($projectSite, $request);

        return to_route('project-sites.index')
            ->with('success', 'Project site created successfully.');
    }

    public function show(ProjectSite $projectSite)
    {
        $projectSite->load($this->editRelations());

        $this->mapSingleRelations($projectSite);

        return Inertia::render('ProjectSites/Show', [
            'projectSite' => $projectSite,
            'centers' => Center::query()->excludingHidden(Centers::hiddenCenters())->orderBy('name', 'asc')->get(['id', 'name', 'code']),
            'partners' => Partner::query()
                ->with('address')
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'code', 'center_id']),
            'projects' => Project::orderBy('name', 'asc')->get(['id', 'name', 'program', 'project_involvement']),
            'dairyBoxes' => DairyBox::query()
                ->with('address')
                ->orderBy('dairy_box_id', 'asc')
                ->get(['id', 'partner_id', 'dairy_box_id', 'business_name', 'manager', 'status', 'launched_date']),
            'enums' => $this->formData->enums(),
        ]);
    }

    public function edit(ProjectSite $projectSite)
    {
        $projectSite->load($this->editRelations());

        $this->mapSingleRelations($projectSite);

        return Inertia::render('ProjectSites/Edit', [
            'projectSite' => $projectSite,
            'centers' => Center::query()->excludingHidden(Centers::hiddenCenters())->orderBy('name', 'asc')->get(['id', 'name', 'code']),
            'partners' => Partner::query()
                ->with('address')
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'code', 'center_id']),
            'projects' => Project::orderBy('name', 'asc')->get(['id', 'name', 'program', 'project_involvement']),
            'dairyBoxes' => DairyBox::query()
                ->with('address')
                ->orderBy('dairy_box_id', 'asc')
                ->get(['id', 'partner_id', 'dairy_box_id', 'business_name', 'manager', 'status', 'launched_date']),
            'enums' => $this->formData->enums(),
        ]);
    }

    private function editRelations(): array
    {
        return [
            'center', 'partner', 'project', 'serviceRecord', 'address',
            'infrastructure.address',
            'marketingInfrastructure.address',
            'productions',
            'equipment',
            'processingStaff',
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

        $this->flattenAddresses($projectSite->getRelation('infrastructure'));
        $projectSite->setAttribute('processing_infrastructure', $projectSite->getRelation('infrastructure'));
        $projectSite->setAttribute('marketing_infrastructure', $projectSite->getRelation('marketingInfrastructure'));
    }

    private function flattenAddresses($items): void
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

    public function update(UpdateProjectSiteRequest $request, ProjectSite $projectSite)
    {
        $validated = $request->validated();

        $centerId = array_key_exists('center_id', $validated) && ! empty($validated['center_id'])
            ? (int) $validated['center_id']
            : null;
        $partnerId = array_key_exists('production_partner_id', $validated) && ! empty($validated['production_partner_id'])
            ? (int) $validated['production_partner_id']
            : null;

        if ($centerId && $partnerId) {
            $partner = Partner::query()->select(['id', 'center_id'])->find($partnerId);

            if (! $partner || (int) $partner->center_id !== $centerId) {
                return back()->withErrors([
                    'production_partner_id' => 'Selected partner does not belong to the selected center.',
                ])->withInput();
            }
        }

        $addressFields = $this->addresses->extract($validated);
        $siteData = $this->addresses->withoutAddress($validated);

        $projectSite->update([
            ...$siteData,
            'center_id' => $centerId,
            'production_partner_id' => $partnerId,
            'project_id' => array_key_exists('project_id', $validated) && ! empty($validated['project_id'])
                ? (int) $validated['project_id']
                : null,
        ]);

        $this->addresses->sync($projectSite, $addressFields);
        $this->relations->sync($projectSite, $request);

        return to_route('project-sites.index')
            ->with('success', 'Project site updated successfully.');
    }

    public function destroy(ProjectSite $projectSite)
    {
        ProjectSite::destroy($projectSite->getKey());

        return to_route('project-sites.index')
            ->with('success', 'Project site deleted successfully.');
    }
}
