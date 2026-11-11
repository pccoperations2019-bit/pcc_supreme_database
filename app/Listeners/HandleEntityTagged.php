<?php

namespace App\Listeners;

use App\Enums\AuditActionEnum;
use App\Enums\DocumentKindEnum;
use App\Enums\DocumentStatusEnum;
use App\Events\EntityTagged;
use App\Models\Document;
use App\Models\DocumentImportDispatch;
use App\Workflows\Concerns\HandlesFailures;
use App\Workflows\ExportBuffaloDerivedProductsWorkflow;
use App\Workflows\ExportCenterPartnersWorkflow;
use App\Workflows\ExportDirectoryDataWorkflow;
use App\Workflows\ExportMilkFeedingProgramsWorkflow;
use App\Workflows\ExportMilkProductionDashboardWorkflow;
use App\Workflows\ExportMilkUtilizationWorkflow;
use App\Workflows\ExportPartnerMembersWorkflow;
use App\Workflows\ExportPL480Workflow;
use App\Workflows\ImportCBEDCenterFarmersMasterlistWorkflow;
use App\Workflows\ImportCBEDCenterMasterlistWorkflow;
use App\Workflows\ImportCBEDCenterMilkFeedingProgramReportWorkflow;
use App\Workflows\ImportCBEDCenterPartnersMasterlistWorkflow;
use App\Workflows\ImportCBINCCDPProjectSiteWorkflow;
use App\Workflows\ImportCenterProductionGoalWorkflow;
use App\Workflows\ImportDairyBoxSalesWorkflow;
use App\Workflows\ImportDairyBoxWorkflow;
use App\Workflows\ImportDocumentTypeWorkflow;
use App\Workflows\ImportIHCenterMasterlistWorkflow;
use App\Workflows\ImportIHMilkConsolidationWorkflow;
use App\Workflows\ImportIHMilkProductionWorkflow;
use App\Workflows\ImportManureProductionWorkflow;
use App\Workflows\ImportMeatProductionWorkflow;
use App\Workflows\ImportMilkProductionYearlyWorkflow;
use App\Workflows\ImportServicesWorkflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Facades\Activity;

class HandleEntityTagged implements ShouldQueue
{
    use HandlesFailures, InteractsWithQueue;

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(EntityTagged $event): array
    {
        $lockKey = "{$event->tagKey}_{$event->tagValue}_{$event->taggableType}_{$event->taggableId}";

        return [
            (new WithoutOverlapping($lockKey))->dontRelease(),
        ];
    }

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(EntityTagged $event): void
    {
        $workflows = [
            'template_institutional_herd_master_list' => ImportIHCenterMasterlistWorkflow::class,
            // Historical Data Workflow for Milk Production
            // 'template_institutional_herd_milk_production' => ImportIHMilkProductionWorkflow::class,
            'template_institutional_herd_milk_production' => ImportMilkProductionYearlyWorkflow::class,
            // 'template_institutional_herd_milk_consolidation_report' => ImportIHMilkConsolidationWorkflow::class,
            'template_cbed_master_list' => ImportCBEDCenterMasterlistWorkflow::class,
            'template_cbin_ccdp_master_list' => ImportCBINCCDPProjectSiteWorkflow::class,
            'template_cbed_partners_master_list' => ImportCBEDCenterPartnersMasterlistWorkflow::class,
            'template_cbed_milk_feeding_program_report' => ImportCBEDCenterMilkFeedingProgramReportWorkflow::class,
            'template_cbed_farmers_master_list' => ImportCBEDCenterFarmersMasterlistWorkflow::class,
            'template_cbed_mfo_services' => ImportServicesWorkflow::class,
            'template_cbed_dairy_box_master_list' => ImportDairyBoxWorkflow::class,
            'template_cbed_dairy_box_sales' => ImportDairyBoxSalesWorkflow::class,
            'template_center_production_goals' => ImportCenterProductionGoalWorkflow::class,
            'template_institutional_herd_manure_production' => ImportManureProductionWorkflow::class,
            'template_institutional_herd_meat_production' => ImportMeatProductionWorkflow::class,
            'report_type_milk-feeding-programs' => ExportMilkFeedingProgramsWorkflow::class,
            'report_type_milk-production-dashboard' => ExportMilkProductionDashboardWorkflow::class,
            'report_type_center-partners' => ExportCenterPartnersWorkflow::class,
            'report_type_partner-members' => ExportPartnerMembersWorkflow::class,
            'report_type_bdp' => ExportBuffaloDerivedProductsWorkflow::class,
            'report_type_directory' => ExportDirectoryDataWorkflow::class,
            'report_type_milk-utilization' => ExportMilkUtilizationWorkflow::class,
            'report_type_pl480' => ExportPL480Workflow::class,
        ];

        $importWorkflow = $this->importWorkflowForEvent($event, $workflows);

        if ($importWorkflow !== null
            && ! $this->claimApprovedSubmission($event, $importWorkflow)) {
            return;
        }

        try {
            // Register shutdown handler for import workflows
            if ($event->taggableType === 'document' && $event->taggableId) {
                $document = Document::query()->find($event->taggableId);
                if ($document) {
                    $this->registerImportShutdownHandler($document);
                }
            }

            if (in_array($event->tagKey, ['document', 'center', 'partner', 'farmer'])) {
                // An export document carries the same `document` tag as an import:
                // older exports were tagged `document=processing` alongside
                // `report_type`. Without this it runs the import workflow and
                // logs "Imported" for a file that was generated, not uploaded.
                // No entry is written here: the `report_type` event below logs
                // the single "Exported" row, so old exports with both tags
                // still produce exactly one.
                if ($this->isExportDocument($event)) {
                    $this->markImportCompleted();

                    return;
                }

                if ($event->taggableType !== 'document' || $event->tagKey !== 'document') {
                    $this->markImportCompleted();

                    return;
                }

                $workflowClass = ImportDocumentTypeWorkflow::class;
                $workflow = new $workflowClass;
                $this->resolveWorkflow($workflow, $event, suppressLogs: true);
                $this->logImportCompleted($event);

                $this->markImportCompleted();

                return;
            }

            $workflowKey = $event->tagKey.'_'.$event->tagValue;

            if (array_key_exists($workflowKey, $workflows)) {
                $workflowClass = $workflows[$workflowKey];
                $workflow = new $workflowClass;
                $this->resolveWorkflow(
                    $workflow,
                    $event,
                    suppressLogs: ! in_array($workflowClass, self::EXPORT_WORKFLOWS, true),
                );

                if (! in_array($workflowClass, self::EXPORT_WORKFLOWS, true)) {
                    $this->logImportCompleted($event);
                } else {
                    // The report_type event is the only one a new export fires
                    // (exports no longer carry a `document` tag), so without
                    // this a generated report leaves no entry naming it.
                    $this->logExportCompleted($event);
                }

                $this->markImportCompleted();
            }
        } catch (\Throwable $e) {
            Log::error('Import workflow failed: '.$e->getMessage(), [
                'event' => $event,
                'exception' => $e,
            ]);

            // Mark document as failed if it's a document import
            if ($event->taggableType === 'document' && $event->taggableId) {
                $this->markDocumentAsFailedByException($event->taggableId, $e->getMessage(), 'queue_import');
            }

            throw $e;
        }
    }

    /**
     * Only template and document-type events can initiate an import. In
     * particular, entity association tags and generated report tags must not
     * claim the document before its actual import event is handled.
     *
     * @param  array<string, class-string>  $workflows
     */
    private function importWorkflowForEvent(EntityTagged $event, array $workflows): ?string
    {
        if ($event->taggableType !== 'document' || ! $event->taggableId || $this->isExportDocument($event)) {
            return null;
        }

        if ($event->tagKey === 'document') {
            return ImportDocumentTypeWorkflow::class;
        }

        $workflow = $workflows[$event->tagKey.'_'.$event->tagValue] ?? null;

        return $workflow !== null && ! in_array($workflow, self::EXPORT_WORKFLOWS, true)
            ? $workflow
            : null;
    }

    /**
     * Atomically claim the one import initiation allowed for this approved
     * submission. Old single-review approvals have no two-stage history and are
     * intentionally not eligible for automatic re-import.
     */
    private function claimApprovedSubmission(EntityTagged $event, string $workflowClass): bool
    {
        if (! $event->taggableId) {
            return false;
        }

        try {
            $document = DB::transaction(function () use ($event, $workflowClass) {
                $document = Document::query()->lockForUpdate()->find($event->taggableId);

                if (! $document?->hasFinalApprovalForCurrentSubmission()) {
                    return null;
                }

                $version = (int) $document->submission_version;
                $currentStatus = $document->status;

                if (! in_array($currentStatus, [
                    DocumentStatusEnum::APPROVED,
                    DocumentStatusEnum::PROCESSING,
                    DocumentStatusEnum::PROCESSED,
                ], true)
                    || ($currentStatus !== DocumentStatusEnum::APPROVED
                        && (int) $document->processing_submission_version !== $version)) {
                    return null;
                }

                DocumentImportDispatch::query()->create([
                    'document_id' => $document->id,
                    'submission_version' => $version,
                    'workflow_key' => $workflowClass,
                ]);

                $document->update([
                    'status' => DocumentStatusEnum::PROCESSING,
                    'processing_submission_version' => $version,
                ]);

                return ['document' => $document->fresh(), 'old_status' => $currentStatus];
            }, 3);
        } catch (QueryException) {
            // A unique document/version/workflow key means this event has
            // already been claimed (including duplicate queue delivery).
            return false;
        }

        if (! $document) {
            return false;
        }

        if ($document['old_status'] !== DocumentStatusEnum::PROCESSING) {
            activity()
                ->performedOn($document['document'])
                ->withProperties([
                    'old_status' => $document['old_status']->value,
                    'new_status' => DocumentStatusEnum::PROCESSING->value,
                    'submission_version' => $document['document']->submission_version,
                    'workflow' => $workflowClass,
                ])
                ->event('document-processing-started')
                ->log('Document Processing Started');
        }

        return true;
    }

    /**
     * The workflows that generate a file from many rows rather than loading one.
     *
     * An export writes a document row, a file row and a status transition, so it
     * produces a handful of entries at most. They used to be suppressed along with
     * the imports, which is why a PL480 export finished - file written, document
     * marked processed - and left nothing at all in the trail.
     *
     * @var list<class-string>
     */
    private const EXPORT_WORKFLOWS = [
        ExportMilkFeedingProgramsWorkflow::class,
        ExportMilkProductionDashboardWorkflow::class,
        ExportCenterPartnersWorkflow::class,
        ExportPartnerMembersWorkflow::class,
        ExportBuffaloDerivedProductsWorkflow::class,
        ExportDirectoryDataWorkflow::class,
        ExportMilkUtilizationWorkflow::class,
        ExportPL480Workflow::class,
    ];

    /**
     * Run a workflow, with model-level audit logging switched off for imports only.
     *
     * An import writes one row per spreadsheet line through `create` /
     * `updateOrCreate`, so leaving model logging on would put one audit entry per
     * imported line into the trail - thousands for a single sheet - and bury the
     * hand-made edits a reviewer is looking for. The import is still visible on
     * the document itself, and a document the import failed on still records its
     * failure, because the suppression is scoped to this call and lifted before the
     * catch block above runs.
     *
     * An export is left alone: it writes one file, and suppressing it means the
     * report a user asked for never appears in the history at all.
     */
    private function resolveWorkflow(object $workflow, EntityTagged $event, bool $suppressLogs): void
    {
        if (! $suppressLogs) {
            $workflow->resolve($event->taggableType, $event->taggableId);

            return;
        }

        Activity::withoutLogs(
            fn () => $workflow->resolve($event->taggableType, $event->taggableId)
        );
    }

    /**
     * Whether the tagged record is a generated report rather than an upload.
     *
     * Both carry a `document` tag, so the tag alone cannot tell them apart.
     * Only generated reports carry `report_type`, which is also how the
     * documents list splits imports from exports.
     */
    private function isExportDocument(EntityTagged $event): bool
    {
        if ($event->taggableType !== 'document' || blank($event->taggableId)) {
            return false;
        }

        $document = Document::query()->find($event->taggableId);

        return $document !== null
            && $document->tags()->where('key', DocumentKindEnum::EXPORT_TAG_KEY)->exists();
    }

    /**
     * Record that a report was generated, once per run.
     *
     * The mirror of logImportCompleted: without it an export finished - file
     * written, document marked processed - and the only entry naming the
     * report said "Imported".
     *
     * Caused by the requester, not the system: the listener runs on the queue
     * with no signed-in user, but the document names who asked for the
     * report, while the processing entries stay system-attributed.
     */
    private function logExportCompleted(EntityTagged $event): void
    {
        $subject = $this->taggable($event);

        $label = $subject instanceof Document
            ? $subject->title
            : str($event->tagKey)
                ->replace(['template_', 'report_type_'], '')
                ->headline()
                ->toString();

        $requester = $subject instanceof Document
            ? $subject->file?->uploadedBy
            : null;

        activity()
            ->causedBy($requester)
            ->performedOn($subject)
            ->event(AuditActionEnum::EXPORTED->value)
            ->log(trim("{$label} Exported"));
    }

    /**
     * Record that a spreadsheet was imported, once per run.
     *
     * An import writes one row per sheet line, and those per-row writes are
     * suppressed so they do not bury the hand-made edits a reviewer is looking
     * for. Left at that, an import left nothing in the trail at all: the file was
     * uploaded, approved and processed, and the rows it wrote were never named.
     * This is the one entry that says which file was brought in, and nothing
     * about how many rows it happened to contain.
     *
     * No causer: the import runs on the queue, and the same reasoning as the
     * processing entries applies - who asked for the work and who carried it out
     * are two different questions, and the document already names the requester.
     */
    private function logImportCompleted(EntityTagged $event): void
    {
        $subject = $this->taggable($event);

        // "template_cbed_dairy_box_sales" -> "Cbed Dairy Box Sales". A document
        // names itself, so its own title says what was brought in better than the
        // tag does.
        $label = $subject instanceof Document
            ? $subject->title
            : str($event->tagKey)
                ->replace(['template_', 'report_type_'], '')
                ->headline()
                ->toString();

        activity()
            ->performedOn($subject)
            ->event(AuditActionEnum::IMPORTED->value)
            ->log(trim("{$label} Imported"));
    }

    /**
     * The model the tag points at, when it still exists.
     */
    private function taggable(EntityTagged $event): ?Model
    {
        if (blank($event->taggableType) || blank($event->taggableId)) {
            return null;
        }

        $model = Relation::getMorphedModel($event->taggableType);

        return $model === null ? null : $model::query()->find($event->taggableId);
    }

    /**
     * Handle a job failure.
     */
    public function failed(EntityTagged $event, \Throwable $exception): void
    {
        Log::error('HandleEntityTagged job failed: '.$exception->getMessage(), [
            'event' => $event,
            'exception' => $exception,
        ]);

        // Mark document as failed if it's a document import
        if ($event->taggableType === 'document' && $event->taggableId) {
            $this->markDocumentAsFailedByException($event->taggableId, $exception->getMessage(), 'queue_import');
        }
    }
}
