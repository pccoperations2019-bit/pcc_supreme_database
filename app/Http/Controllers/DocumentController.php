<?php

namespace App\Http\Controllers;

use App\Actions\Document\CreateDocumentAction;
use App\Actions\Document\DescribeDocumentAction;
use App\Actions\Document\UpdateDocumentAction;
use App\Actions\Tag\CreateTagAction;
use App\Actions\Tag\UpsertTagAction;
use App\Enums\DocumentEnum;
use App\Enums\DocumentKindEnum;
use App\Enums\DocumentStatusEnum;
use App\Enums\ProgramEnum;
use App\Enums\UserRoleEnum;
use App\Events\EntityTagged;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Models\Center;
use App\Models\Document;
use App\Models\Farmer;
use App\Models\Partner;
use App\Models\User;
use App\Support\Centers;
use App\Support\Documents\DocumentOwnershipResolver;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DocumentController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            // Center admins own their uploads, but deleting a document (and the
            // file behind it) stays an agency-level job.
            if ($request->user()?->isCenterScoped()
                && $request->routeIs('documents.destroy')) {
                abort(403);
            }

            return $next($request);
        });
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = QueryBuilder::for(Document::class)
            ->allowedFilters([
                AllowedFilter::partial('title'),
                AllowedFilter::partial('description'),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('kind', function ($query, $value) {
                    if ($value === DocumentKindEnum::EXPORT->value) {
                        $query->kindExport();

                        return;
                    }

                    $query->kindImport();
                }),
            ])
            ->allowedSorts([
                'title',
                'created_at',
                'published_at',
            ])
            ->with([
                'tags',
                'file',
                'file.uploadedBy',
                'reviewedBy',
                'centerReviewedBy',
                'agencyReviewedBy',
            ])
            ->defaultSort('-created_at');

        $documents = $query
            ->when($this->isCenterScoped(), fn (Builder $builder) => $this->scopeToAdminCenter($builder))
            ->when(! $this->isCenterScoped(), fn (Builder $builder) => $this->scopeToUserAgency($builder))

            ->when(! request()->filled('filter.kind'), fn (Builder $builder) => $builder->kindImport())
            ->paginate(10)
            ->appends(request()->query());

        $ownershipResolver = app(DocumentOwnershipResolver::class);
        $documents->getCollection()->transform(function (Document $document) use ($ownershipResolver) {
            $ownership = $ownershipResolver->resolve($document);
            $document->setAttribute('originating_center', $ownership ? [
                'id' => $ownership['center']->id,
                'name' => $ownership['center']->name,
                'code' => $ownership['center']->code,
            ] : null);

            return $document;
        });

        return Inertia::render('Documents/Index', [
            'documents' => $documents,
            'enums' => [
                'documentStatuses' => DocumentStatusEnum::labels(),
                'documentKinds' => DocumentKindEnum::labels(),
            ],
            // No single document on the index, so can_review reports whether the
            // role reviews at all; each row is still gated server side. Named
            // documentPermissions, not permissions: that key is shared globally
            // by HandleInertiaRequests and a page prop would shadow it, which
            // strips every permission-gated nav link on this page.
            'documentPermissions' => $this->reviewPermissions(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $tenant = resolve(TenantContext::class);

        // Define templates in future example: Meat and Manure Production
        $templatesRequiringProgram = [
            // No templates requiring program for now
        ];

        return Inertia::render('Documents/Create', [
            'tagOptions' => [
                'template' => [
                    [
                        'label' => 'Center - Master List',
                        'key' => 'institutional_herd_master_list',
                    ],
                    // Historical Data Workflow for Milk Production
                    // [
                    //     'label' => 'Inititutional Herd - Milk Production',
                    //     'key' => 'institutional_herd_milk_production',
                    // ],
                    [
                        'label' => 'Milk Production',
                        'key' => 'institutional_herd_milk_production',
                    ],
                    [
                        'label' => 'Manure Production',
                        'key' => 'institutional_herd_manure_production',
                    ],
                    [
                        'label' => 'Meat Production',
                        'key' => 'institutional_herd_meat_production',
                    ],
                    // [
                    //     'label' => 'Institutional Herd - Milk Consolidation Report',
                    //     'key' => 'institutional_herd_milk_consolidation_report',
                    // ],
                    [
                        'label' => 'CBED - Master List',
                        'key' => 'cbed_master_list',
                    ],
                    [
                        'label' => 'CBED - Partners Master List',
                        'key' => 'cbed_partners_master_list',
                    ],
                    [
                        'label' => 'CBED - Milk Feeding Program',
                        'key' => 'cbed_milk_feeding_program_report',
                    ],
                    [
                        'label' => 'CBED - Farmers Master List',
                        'key' => 'cbed_farmers_master_list',
                    ],
                    [
                        'label' => 'CBED - MFO Services',
                        'key' => 'cbed_mfo_services',
                    ],
                    [
                        'label' => 'CBED - Dairy Box - Master List',
                        'key' => 'cbed_dairy_box_master_list',
                    ],
                    [
                        'label' => 'CBED - Dairy Box - Sales',
                        'key' => 'cbed_dairy_box_sales',
                    ],
                    [
                        'label' => 'Center - Production Goals',
                        'key' => 'center_production_goals',
                    ],
                    [
                        'label' => 'CBIN/CCDP - Project Sites Master List',
                        'key' => 'cbin_ccdp_master_list',
                    ],

                ],
                'document' => collect(DocumentEnum::cases())
                    ->map(fn ($document) => [
                        'label' => DocumentEnum::labels()[$document->value],
                        'key' => $document->value,
                    ])
                    ->values()
                    ->all(),
                'entity_type' => [
                    ['label' => 'Center', 'key' => 'center'],
                    ['label' => 'Partners', 'key' => 'partner'],
                    ['label' => 'Farmers', 'key' => 'farmer'],
                ],
                'center' => Center::query()
                    ->excludingHidden(Centers::hiddenCenters())
                    ->when(
                        $tenant->agencyId(),
                        fn ($query) => $query->where('agency_id', $tenant->agencyId())
                    )
                    ->when(
                        $this->isCenterScoped(),
                        fn ($query) => $query->where('id', $tenant->centerId())
                    )
                    ->with('agency')
                    ->get()
                    ->map(fn ($center) => [
                        'label' => $center->code.' - '.$center->name,
                        'key' => $center->code,
                    ])
                    ->values()
                    ->all(),
                'program' => collect(ProgramEnum::options())
                    ->map(fn (array $option) => [
                        'label' => $option['label'],
                        'key' => $option['value'],
                    ])
                    ->values()
                    ->all(),
            ],
            'templatesRequiringProgram' => $templatesRequiringProgram,

            'centerTagValue' => $this->isCenterScoped() ? $this->adminCenterCode() : null,
            'allowedFileTypes' => [
                'extensions' => config('documents.allowed_extensions', ['pdf', 'xlsx', 'xls', 'csv']),
                'mimetypes' => config('documents.allowed_mimetypes', []),
                'accept' => config('documents.accept_attribute', '.pdf,.xlsx,.xls,.csv'),
                'acceptLabel' => config('documents.accept_label', 'PDF, Excel (.xlsx, .xls) or CSV'),
                'maxSizeMb' => config('documents.max_size_mb', 20),
            ],
        ]);
    }

    public function store(StoreDocumentRequest $request, CreateDocumentAction $createDocumentAction, CreateTagAction $createTagAction)
    {
        $tags = $request->input('tags', []);

        if ($this->isCenterScoped()) {
            $tags = $this->forceAdminCenterTag($tags);
        }

        $this->validateOwnershipTags($tags, $request->user());

        abort_if(
            $this->isReviewer(),
            403,
            'A reviewer cannot upload documents.'
        );

        $document = $createDocumentAction->execute(
            title: $request->input('title'),
            fileName: $request->input('file_name'),
            fileKey: $request->input('file_key'),
            fileType: $request->input('file_type'),
            fileSize: $request->input('file_size'),
            uploadedBy: $request->user()->id,
            description: $request->input('description'),
            registrationNumber: $request->input('registration_number'),
            validity: $request->input('validity'),
            complianceStatus: $request->input('compliance_status'),

            status: DocumentStatusEnum::DRAFT,
        );

        foreach ($tags as $tag) {
            $createTagAction->execute(
                key: $tag['key'],
                value: $tag['value'],
                taggableType: Str::of(class_basename(Document::class))->lower(),
                taggableId: $document->id
            );
        }

        if ($request->user()->agency_id !== null) {
            $this->authorizeDocumentAccess($document);
        }

        CreateDocumentAction::updateEntityBooleanFlags($document);

        $this->logActivity($request, $document, DocumentStatusEnum::DRAFT, 'Document Uploaded');

        if ($this->isCenterScoped() || app(DocumentOwnershipResolver::class)->resolve($document) === null) {
            // Center admins submit into the center stage; a center-less upload
            // has no center stage, so it goes straight to agency review.
            $this->markPendingReview($request, $document);
        }

        if ($request->wantsJson()) {
            return response()->json(['document' => $document], 201);
        }

        return to_route('documents.index')->with('success', 'Document created successfully.');
    }

    public function show(Document $document)
    {
        $this->authorizeDocumentAccess($document);
        $document->load(['file', 'file.uploadedBy', 'tags', 'reviewedBy', 'centerReviewedBy', 'agencyReviewedBy']);
        $document->load(['activities' => fn ($query) => $query
            ->with('causer')
            ->oldest()
            ->limit(50),
        ]);

        return Inertia::render('Documents/Show', [
            'document' => $document,
            'details' => app(DescribeDocumentAction::class)->execute($document),
            'documentPermissions' => $this->reviewPermissions($document),
        ]);
    }

    public function showDetails(Document $document)
    {
        $this->authorizeDocumentAccess($document);

        $document->load(['file', 'file.uploadedBy', 'reviewedBy', 'centerReviewedBy', 'agencyReviewedBy', 'tags']);

        return response()->json([
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'status' => $document->status?->value,
                'reviewed_by' => $document->reviewedBy?->name,
                'center_reviewed_by' => $document->centerReviewedBy?->name,
                'agency_reviewed_by' => $document->agencyReviewedBy?->name,
                'created_at' => $document->created_at?->toIso8601String(),
                'metadata' => $document->metadata,
            ],
            ...app(DescribeDocumentAction::class)->execute($document),
        ]);
    }

    public function showQueuedImport(Document $document)
    {
        $this->authorizeDocumentAccess($document);
        $document->load(['file']);

        return response()->json([
            'id' => $document->id,
            'status' => $document->status?->value,
            'progress' => $document->progress,
            'file' => $document->file ? [
                'name' => $document->file->name,
                'size' => $document->file->size,
            ] : null,
        ]);
    }

    public function edit(Request $request, Document $document)
    {
        $this->authorizeDocumentAccess($document);
        $this->authorizeDocumentAction($document);

        $document->load(['file', 'file.uploadedBy', 'tags', 'reviewedBy']);

        return Inertia::render('Documents/Edit', [
            'document' => $document,
        ]);
    }

    public function update(UpdateDocumentRequest $request, Document $document, UpdateDocumentAction $updateDocumentAction, UpsertTagAction $upsertTagAction)
    {
        $this->authorizeDocumentAccess($document);
        $this->authorizeDocumentAction($document);
        $this->validateOwnershipTags($this->ownershipTagsForUpdate($request, $document), $request->user());

        $document = $updateDocumentAction->execute(
            document: $document,
            title: $request->input('title'),
            description: $request->input('description'),
            registrationNumber: $request->input('registration_number'),
            validity: $request->input('validity'),
            complianceStatus: $request->input('compliance_status'),
            fileName: $request->input('file_name'),
            fileKey: $request->input('file_key'),
            fileType: $request->input('file_type'),
            fileSize: $request->input('file_size'),
        );

        $documentType = $request->input('document_type');
        if ($documentType !== null) {

            $document->tags()->where('key', 'document')->delete();

            if ($documentType !== '') {
                $upsertTagAction->execute(
                    key: 'document',
                    value: $documentType,
                    taggableType: Str::of(class_basename(Document::class))->lower(),
                    taggableId: $document->id
                );
            }
        }

        $centerCode = $this->isCenterScoped()
            ? $this->adminCenterCode()
            : $request->input('center_code');
        if ($centerCode !== null) {
            $upsertTagAction->execute(
                key: 'center',
                value: $centerCode,
                taggableType: Str::of(class_basename(Document::class))->lower(),
                taggableId: $document->id
            );
        }

        $partnerCode = $request->input('partner_code');
        if ($partnerCode !== null) {
            $upsertTagAction->execute(
                key: 'partner',
                value: $partnerCode,
                taggableType: Str::of(class_basename(Document::class))->lower(),
                taggableId: $document->id
            );
        }

        $farmerCode = $request->input('farmer_code');
        if ($farmerCode !== null) {
            $upsertTagAction->execute(
                key: 'farmer',
                value: $farmerCode,
                taggableType: Str::of(class_basename(Document::class))->lower(),
                taggableId: $document->id
            );
        }

        // Update entity boolean flags based on document type
        CreateDocumentAction::updateEntityBooleanFlags($document);

        return to_route('documents.show', $document->id)->with('success', 'Document updated successfully.');
    }

    /** Submit a draft or rejected submission into the center review stage. */
    public function submit(Request $request, Document $document)
    {
        abort_unless($request->user()->can('submit-documents'), 403);
        $this->authorizeDocumentAccess($document);
        $this->authorizeDocumentAction($document);

        abort_unless(
            in_array($document->status, [
                DocumentStatusEnum::DRAFT,
                DocumentStatusEnum::REJECTED,
                DocumentStatusEnum::REJECTED_BY_CENTER,
                DocumentStatusEnum::REJECTED_BY_AGENCY,
            ], true),
            422,
            'Only a draft or rejected document can be submitted for center review.'
        );

        // A center-scoped document must resolve to a center; an agency-level
        // (center-less) document does not, and goes straight to agency review.
        abort_unless(
            app(DocumentOwnershipResolver::class)->resolve($document) !== null
                || ! $request->user()->isCenterScoped(),
            422,
            'The document must have a valid center and agency association before review.'
        );

        $this->markPendingReview($request, $document);

        return back()->with('success', 'Document submitted for center review.');
    }

    private function markPendingReview(Request $request, Document $document): void
    {
        $previousStatus = $document->status;

        // Center-less documents skip the center stage: there is no center to
        // review them, so they go straight to agency review.
        $nextStatus = app(DocumentOwnershipResolver::class)->resolve($document)
            ? DocumentStatusEnum::PENDING_CENTER_REVIEW
            : DocumentStatusEnum::PENDING_AGENCY_REVIEW;

        $resubmission = in_array($previousStatus, [
            DocumentStatusEnum::REJECTED,
            DocumentStatusEnum::REJECTED_BY_CENTER,
            DocumentStatusEnum::REJECTED_BY_AGENCY,
        ], true);
        $version = (int) ($document->submission_version ?: 1) + ($resubmission ? 1 : 0);

        DB::transaction(function () use ($document, $previousStatus, $version, $nextStatus) {
            $updated = Document::query()
                ->whereKey($document->getKey())
                ->where('status', $previousStatus->value)
                ->update([
                    'status' => $nextStatus->value,
                    'submitted_at' => now(),
                    'submission_version' => $version,
                    'processing_submission_version' => null,
                    'center_reviewed_by' => null,
                    'center_reviewed_at' => null,
                    'agency_reviewed_by' => null,
                    'agency_reviewed_at' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'rejection_reason' => null,
                ]);

            abort_if($updated !== 1, 409, 'The document changed while it was being submitted.');
        });

        $document->refresh();

        $this->logActivity(
            $request,
            $document->fresh(),
            $nextStatus,
            'Document Submitted for '.($nextStatus === DocumentStatusEnum::PENDING_CENTER_REVIEW ? 'Center' : 'Agency').' Review',
            ['old_status' => $previousStatus?->value]
        );

    }

    public function approve(Request $request, Document $document)
    {
        $this->authorizeDocumentAccess($document);
        $stage = $this->reviewStageFor($request->user(), $document);
        $result = $this->recordReviewDecision($request, $document, $stage, 'approved', null);

        if (! $result) {
            return back()->with('error', 'This document has already been reviewed or is no longer awaiting this review stage.');
        }

        if ($stage === 'center') {
            return back()->with('success', 'Center review approved. The document is awaiting agency review.');
        }

        // recordReviewDecision has committed both the approval and its history
        // before this existing processing event chain is entered.
        $this->dispatchProcessing($result->fresh());

        return back()->with('success', 'Agency review approved. Processing has started.');
    }

    public function reject(Request $request, Document $document)
    {
        $this->authorizeDocumentAccess($document);
        $stage = $this->reviewStageFor($request->user(), $document);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

        $rejected = $this->recordReviewDecision(
            $request,
            $document,
            $stage,
            'rejected',
            $validated['rejection_reason'],
        );

        if (! $rejected) {
            return back()->with('error', 'This document has already been reviewed.');
        }

        return back()->with('success', ucfirst($stage).' review rejected the document and returned it to the center.');
    }

    private function dispatchProcessing(Document $document): void
    {
        $document->load('tags');

        foreach ($document->tags as $tag) {
            event(new EntityTagged(
                tagKey: $tag->key,
                tagValue: $tag->value,
                taggableType: Str::of(class_basename(Document::class))->lower(),
                taggableId: $document->id,
            ));
        }
    }

    /**
     * Record a workflow transition in the activity log - the single source of
     * truth for a document's history.
     *
     * @param  array<string, mixed>  $properties
     */
    private function logActivity(
        Request $request,
        Document $document,
        DocumentStatusEnum $newStatus,
        string $description,
        array $properties = [],
    ): void {
        activity()
            ->causedBy($request->user())
            ->performedOn($document)
            ->withProperties(array_merge([
                'new_status' => $newStatus->value,
            ], $properties))
            ->event(str($description)->slug()->toString())
            ->log($description);
    }

    private function reviewPermissions(?Document $document = null): array
    {
        $user = request()->user();

        return [
            'can_review' => $document
                ? ($this->mayReviewStage($user, $document, 'center') || $this->mayReviewStage($user, $document, 'agency'))
                : ($this->hasReviewRole($user)),
            'can_review_center' => $document
                ? $this->mayReviewStage($user, $document, 'center')
                : ($user?->hasRole(UserRoleEnum::REVIEWER->value) && $user->center_id !== null && $user->can('review-documents')),
            'can_review_agency' => $document
                ? $this->mayReviewStage($user, $document, 'agency')
                : (($this->isPccAdmin() || ($user?->hasRole(UserRoleEnum::REVIEWER->value) && $user->center_id === null))
                    && $user?->agency_id !== null && $user->can('review-documents')),
            // Deleting a document cascades to its file, so it stays PCC-level.
            'can_delete' => $this->isPccAdmin(),
            'can_edit' => ! $this->isReviewer() && (! $document || $document->isEditableByUploader()),
        ];
    }

    private function hasReviewRole(?User $user): bool
    {
        return $user !== null
            && $user->can('review-documents')
            && ($user->hasRole(UserRoleEnum::AGENCY_ADMIN->value)
                || $user->hasRole(UserRoleEnum::REVIEWER->value));
    }

    private function mayReviewStage(?User $user, Document $document, string $stage): bool
    {
        if (! $user || ! $user->can('review-documents')) {
            return false;
        }

        $ownership = app(DocumentOwnershipResolver::class)->resolve($document);
        $agencyId = $ownership['agency_id'] ?? $document->file?->uploadedBy?->agency_id;

        if ($agencyId === null || $user->agency_id === null || (int) $user->agency_id !== (int) $agencyId) {
            return false;
        }

        $uploadedBy = $document->file?->uploaded_by;

        // Center-reviewed documents need a second pair of eyes. Agency-level
        // (center-less) documents have no center stage and usually only the
        // uploader holds a review role, so they keep the pre-two-level approval
        // behavior.
        // shortcut: a PCC admin can approve their own agency-level upload; add a
        // second agency-wide reviewer when separation of duties is required.
        if ($ownership !== null && (int) $uploadedBy === (int) $user->id) {
            return false;
        }

        if ($stage === 'center') {
            return $ownership !== null
                && $document->status === DocumentStatusEnum::PENDING_CENTER_REVIEW
                && $user->hasRole(UserRoleEnum::REVIEWER->value)
                && $user->center_id !== null
                && (int) $user->center_id === (int) $ownership['center']->id;
        }

        if ($document->status !== DocumentStatusEnum::PENDING_AGENCY_REVIEW) {
            return false;
        }

        $isAgencyReviewer = $user->hasRole(UserRoleEnum::AGENCY_ADMIN->value)
            || ($user->hasRole(UserRoleEnum::REVIEWER->value) && $user->center_id === null);

        if (! $isAgencyReviewer) {
            return false;
        }

        if ($ownership === null) {
            return true;
        }

        return $document->center_reviewed_by !== null
            && (int) $document->center_reviewed_by !== (int) $user->id;
    }

    private function reviewStageFor(User $user, Document $document): string
    {
        if ($document->status === DocumentStatusEnum::PENDING_CENTER_REVIEW) {
            abort_unless($this->mayReviewStage($user, $document, 'center'), 403,
                'Only an authorized reviewer assigned to this center can review this stage.');

            return 'center';
        }

        if ($document->status === DocumentStatusEnum::PENDING_AGENCY_REVIEW) {
            abort_unless($this->mayReviewStage($user, $document, 'agency'), 403,
                'Only an authorized reviewer assigned to this agency can review this stage.');

            return 'agency';
        }

        abort(403, 'This document is not awaiting an available review stage.');
    }

    private function recordReviewDecision(
        Request $request,
        Document $document,
        string $stage,
        string $decision,
        ?string $reason,
    ): ?Document {
        return DB::transaction(function () use ($request, $document, $stage, $decision, $reason) {
            $locked = Document::query()->lockForUpdate()->findOrFail($document->getKey());
            $expected = $stage === 'center'
                ? DocumentStatusEnum::PENDING_CENTER_REVIEW
                : DocumentStatusEnum::PENDING_AGENCY_REVIEW;

            if ($locked->status !== $expected || ! $this->mayReviewStage($request->user(), $locked, $stage)) {
                return null;
            }

            $version = (int) $locked->submission_version;
            $reviewerId = (int) $request->user()->id;
            $now = now();
            $approved = $decision === 'approved';
            $newStatus = match ([$stage, $decision]) {
                ['center', 'approved'] => DocumentStatusEnum::PENDING_AGENCY_REVIEW,
                ['center', 'rejected'] => DocumentStatusEnum::REJECTED_BY_CENTER,
                ['agency', 'approved'] => DocumentStatusEnum::APPROVED,
                ['agency', 'rejected'] => DocumentStatusEnum::REJECTED_BY_AGENCY,
            };

            $attributes = [
                'status' => $newStatus->value,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => $now,
                'rejection_reason' => $reason,
            ];

            if ($stage === 'center') {
                $attributes['center_reviewed_by'] = $reviewerId;
                $attributes['center_reviewed_at'] = $now;
            } else {
                $attributes['agency_reviewed_by'] = $reviewerId;
                $attributes['agency_reviewed_at'] = $now;
            }

            $updated = Document::query()
                ->whereKey($locked->getKey())
                ->where('status', $expected->value)
                ->where('submission_version', $version)
                ->update($attributes);

            if ($updated !== 1) {
                throw new \RuntimeException('The document review state changed during the decision.');
            }

            $fresh = $locked->fresh();
            $this->logActivity(
                $request,
                $fresh,
                $newStatus,
                ucfirst($stage).' Review '.ucfirst($decision),
                [
                    'review_stage' => $stage,
                    'decision' => $decision,
                    'submission_version' => $version,
                    'old_status' => $expected->value,
                    'rejection_reason' => $reason,
                ],
            );

            return $fresh;
        }, 3);
    }

    /**
     * Editing, submitting and deleting are the uploader's business, and only
     * while the document is still theirs to change.
     *
     * The reviewer is denied explicitly. Previously this method returned early
     * for anyone who was not a center admin, so a reviewer — a role that did
     * not exist — would have inherited unrestricted edit access.
     */
    private function authorizeDocumentAction(Document $document): void
    {
        if ($this->isReviewer()) {
            abort(403, 'A reviewer cannot change documents.');
        }

        abort_unless(request()->user()?->can('submit-documents'), 403,
            'You do not have permission to submit documents.');

        if ($this->isCenterScoped()) {
            abort_unless(
                $this->belongsToAdminCenter($document),
                404
            );
        }

        abort_unless(
            $document->isEditableByUploader(),
            403,
            'This document can no longer be changed.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Document $document)
    {
        $this->authorizeDocumentAccess($document);
        // Deleting cascades to the underlying file, so it stays PCC-level. This
        // had no gate at all before, which let any signed-in account remove any
        // document by id.
        abort_unless($this->isPccAdmin(), 403, 'Only a PCC administrator can delete documents.');

        // Load the file relationship to access it before deletion
        $document->load('file');

        // Delete the document (will cascade delete the file due to foreign key constraint)
        Document::destroy($document->getKey());

        return to_route('documents.index')->with('success', 'Document deleted successfully.');
    }

    /**
     * Whether the document references the center admin's own center, either
     * directly or via one of their partners/farmers.
     */
    private function belongsToAdminCenter(Document $document): bool
    {
        return $this->scopeToAdminCenter(Document::query()->whereKey($document->getKey()))->exists();
    }

    private function authorizeDocumentAccess(Document $document): void
    {
        $user = request()->user();

        if ($user?->isCenterScoped()) {
            abort_unless($this->belongsToAdminCenter($document), 404);

            $assignedCenter = $user->center;
            abort_unless(
                $assignedCenter !== null
                    && ($user->agency_id === null || (int) $user->agency_id === (int) $assignedCenter->agency_id),
                404,
            );

            return;
        }

        $ownership = app(DocumentOwnershipResolver::class)->resolve($document);
        $agencyId = $ownership['agency_id'] ?? $document->file?->uploadedBy?->agency_id;
        abort_unless(
            $agencyId !== null
                && $user?->agency_id !== null
                && (int) $user->agency_id === (int) $agencyId,
            404,
        );
    }

    private function scopeToUserAgency(Builder $query): Builder
    {
        $agencyId = request()->user()?->agency_id;

        if ($agencyId === null) {
            return $query->whereRaw('1 = 0');
        }

        $centerIds = Center::query()->where('agency_id', $agencyId)->pluck('id');
        $codesByKey = [
            'center' => Center::query()->whereIn('id', $centerIds)->pluck('code')->all(),
            'partner' => Partner::query()->whereIn('center_id', $centerIds)->pluck('code')->all(),
            'farmer' => Farmer::query()->whereIn('center_id', $centerIds)->pluck('code')->all(),
        ];

        return $query->where(function (Builder $builder) use ($codesByKey, $agencyId) {
            if (array_sum(array_map('count', $codesByKey)) > 0) {
                $builder->whereHas('tags', function ($tagQuery) use ($codesByKey) {
                    $tagQuery->where(function ($tagQuery) use ($codesByKey) {
                        foreach ($codesByKey as $key => $codes) {
                            if ($codes === []) {
                                continue;
                            }

                            $tagQuery->orWhere(fn ($nested) => $nested->where('key', $key)->whereIn('value', $codes));
                        }
                    });
                });
            }

            // Agency-level documents have no center/partner/farmer tag, so they
            // belong to the agency of whoever uploaded them.
            $builder->orWhere(function (Builder $nested) use ($agencyId) {
                $nested->whereDoesntHave('tags', fn ($tagQuery) => $tagQuery->whereIn('key', ['center', 'partner', 'farmer']))
                    ->whereHas('file.uploadedBy', fn ($userQuery) => $userQuery->where('agency_id', $agencyId));
            });
        });
    }

    /**
     * Replace any submitted `center` tag with the center admin's own center, so
     * an uploaded document always belongs to the uploader's center.
     *
     * @param  array<int, array<string, mixed>>  $tags
     * @return array<int, array<string, mixed>>
     */
    private function forceAdminCenterTag(array $tags): array
    {
        $centerCode = $this->adminCenterCode();

        abort_if($centerCode === null, 403);

        $tags = array_values(array_filter(
            $tags,
            fn ($tag) => ($tag['key'] ?? null) !== 'center'
        ));

        $tags[] = ['key' => 'center', 'value' => $centerCode];

        return $tags;
    }

    /** Ensure ownership tags resolve to one center inside the actor's agency. */
    private function validateOwnershipTags(array $tags, User $user): void
    {
        $centerIds = [];

        foreach ($tags as $tag) {
            $key = $tag['key'] ?? null;
            $value = $tag['value'] ?? null;

            if (! in_array($key, ['center', 'partner', 'farmer'], true)) {
                continue;
            }

            $centerId = match ($key) {
                'center' => Center::query()->where('code', $value)->value('id'),
                'partner' => Partner::query()->where('code', $value)->value('center_id'),
                'farmer' => Farmer::query()->where('code', $value)->value('center_id'),
            };

            abort_if($centerId === null, 422, 'A center, partner, or farmer tag does not resolve to a valid center.');
            $centerIds[(int) $centerId] = true;
        }

        // A PCC-level upload may belong to no center at all. Center-scoped users
        // always get one forced on, so an empty set here means they slipped past
        // that and must not upload.
        if ($centerIds === []) {
            abort_if($user->isCenterScoped(), 422,
                'The document must be associated with exactly one center before submission.');

            return;
        }

        abort_unless(count($centerIds) === 1, 422,
            'The document must be associated with exactly one center before submission.');

        $center = Center::query()->find(array_key_first($centerIds));
        $agencyId = $user->agency_id ?? $user->center?->agency_id;

        abort_unless($center && $agencyId !== null && (int) $center->agency_id === (int) $agencyId, 403,
            'The document center is outside your assigned agency.');

        if ($user->isCenterScoped()) {
            abort_unless((int) $center->id === (int) $user->center_id, 403,
                'The document center is outside your assigned center.');
        }
    }

    /** @return array<int, array{key: string, value: string}> */
    private function ownershipTagsForUpdate(Request $request, Document $document): array
    {
        $tags = $document->tags()
            ->whereIn('key', ['center', 'partner', 'farmer'])
            ->get(['key', 'value'])
            ->map(fn ($tag) => ['key' => $tag->key, 'value' => $tag->value])
            ->all();

        foreach (['center' => 'center_code', 'partner' => 'partner_code', 'farmer' => 'farmer_code'] as $key => $input) {
            if ($request->exists($input)) {
                $tags = array_values(array_filter($tags, fn ($tag) => $tag['key'] !== $key));
                if ($request->filled($input)) {
                    $tags[] = ['key' => $key, 'value' => (string) $request->input($input)];
                }
            }
        }

        if ($this->isCenterScoped()) {
            $tags = $this->forceAdminCenterTag($tags);
        }

        return $tags;
    }

    /**
     * Whether the caller is confined to a single center — the encoder and the
     * reviewer both are. All center tagging and center scoping keys off this,
     * not off a role name, so a new center-level role is covered by adding it to
     * UserRoleEnum rather than by remembering to edit this file.
     */
    private function isCenterScoped(): bool
    {
        return (bool) request()->user()?->isCenterScoped();
    }

    /**
     * The center's reviewer: may approve or reject their own center's documents,
     * and never edits or uploads one.
     */
    private function isReviewer(): bool
    {
        return (bool) request()->user()?->hasRole(UserRoleEnum::REVIEWER->value);
    }

    private function isPccAdmin(): bool
    {
        return (bool) request()->user()?->hasRole(UserRoleEnum::AGENCY_ADMIN->value);
    }

    /**
     * The center admin's own center code, which is how documents reference a
     * center (tags hold codes, not ids).
     */
    private function adminCenterCode(): ?string
    {
        $centerId = request()->user()?->center_id;

        if ($centerId === null) {
            return null;
        }

        return Center::find($centerId)?->code;
    }

    /**
     * Documents belong to a center either directly (a `center` tag) or through a
     * partner/farmer that belongs to that center, so match all three.
     */
    private function scopeToAdminCenter(Builder $query): Builder
    {
        $centerCode = $this->adminCenterCode();

        if ($centerCode === null) {
            // No center to scope to, so show nothing rather than everything.
            return $query->whereRaw('1 = 0');
        }

        $codesByKey = [
            'center' => [$centerCode],
            'partner' => Partner::where('center_id', request()->user()->center_id)->pluck('code')->all(),
            'farmer' => Farmer::where('center_id', request()->user()->center_id)->pluck('code')->all(),
        ];

        return $query->whereHas('tags', function ($tagQuery) use ($codesByKey) {
            $tagQuery->where(function ($tagQuery) use ($codesByKey) {
                foreach ($codesByKey as $key => $codes) {
                    if ($codes === []) {
                        continue;
                    }

                    $tagQuery->orWhere(function ($tagQuery) use ($key, $codes) {
                        $tagQuery->where('key', $key)->whereIn('value', $codes);
                    });
                }
            });
        });
    }
}
