<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DairyBox;
use App\Models\ProjectSite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Persists the nested, "replace on save" child records that come in alongside
 * a ProjectSite payload (productions, infrastructure, staff, training, budget,
 * legal documents). Records with an 'id' are updated in place; records
 * without one are created; anything previously saved but missing from the
 * incoming payload is deleted.
 *
 * IMPORTANT: sync() performs multiple delete/create/update calls per relation
 * and is NOT wrapped in its own transaction, so a failure partway through
 * (e.g. a DB constraint violation on record 3 of 5) can leave a relation
 * partially replaced. Callers MUST wrap sync() in DB::transaction() alongside
 * the ProjectSite save it belongs to — see CBINProjectSiteController for the
 * expected usage.
 */
class ProjectSiteRelationsService
{
    /** input key => Eloquent relation method name */
    private const RELATIONS = [
        'productions' => 'productions',
        'processing_infrastructure' => 'infrastructure',
        'equipment' => 'equipment',
        'processing_staff' => 'processingStaff',
        'marketing_staff' => 'marketingStaff',
        'marketing_infrastructure' => 'marketingInfrastructure',
        'training_records' => 'trainingRecords',
    ];

    /** Infrastructure input key => nested staff input and relation details. */
    private const NESTED_STAFF_RELATIONS = [
        'processing_infrastructure' => [
            'input_key' => 'processing_staff',
            'relation' => 'processingStaff',
        ],
        'marketing_infrastructure' => [
            'input_key' => 'marketing_staff',
            'relation' => 'marketingStaff',
        ],
    ];

    /** Which of the above relations carry their own nested address. */
    private const RELATIONS_WITH_ADDRESS = [
        'processing_infrastructure',
        'marketing_infrastructure',
    ];

    public function __construct(
        private readonly ProjectSiteAddressService $addresses,
    ) {}

    public function sync(ProjectSite $projectSite, Request $request): void
    {
        $nestedStaffInputKeys = [];
        foreach (self::NESTED_STAFF_RELATIONS as $infrastructureKey => $staff) {
            $records = $this->normalizeRecords($request->input($infrastructureKey));
            if (collect($records)->contains(fn (array $record) => array_key_exists($staff['input_key'], $record))) {
                $nestedStaffInputKeys[$staff['input_key']] = true;
            }
        }

        foreach (self::RELATIONS as $inputKey => $relationMethod) {
            // Nested staff is authoritative when supplied. Otherwise retain the
            // legacy top-level processing_staff/marketing_staff behavior.
            if (isset($nestedStaffInputKeys[$inputKey])) {
                continue;
            }

            // request->has() (not input()) so an explicitly-submitted empty
            // array ([]) is distinguished from the key being absent
            // entirely. Absent = leave this relation untouched. Present but
            // empty = the user cleared every row; delete them all.
            if (! $request->has($inputKey)) {
                continue;
            }

            $records = $this->normalizeRecords($request->input($inputKey));
            $nestedStaff = self::NESTED_STAFF_RELATIONS[$inputKey] ?? null;

            $existingIds = array_values(array_filter(
                array_column($records, 'id'),
                static fn ($id) => $id !== null && $id !== ''
            ));

            $existingIds !== []
                ? $projectSite->{$relationMethod}()->whereNotIn('id', $existingIds)->delete()
                : $projectSite->{$relationMethod}()->delete();

            foreach ($records as $record) {
                $nestedStaffRecords = $nestedStaff !== null && array_key_exists($nestedStaff['input_key'], $record)
                    ? $record[$nestedStaff['input_key']]
                    : null;

                // Tested here, while the nested staff is still on the record: a row
                // whose only content is the staff hanging off it is worth keeping,
                // and once the key is unset there is nothing left to tell.
                if ($this->isBlank($record)) {
                    continue;
                }

                if ($nestedStaff !== null) {
                    unset($record[$nestedStaff['input_key']]);
                }

                $item = empty($record['id'])
                    ? $this->createRecord($projectSite, $relationMethod, $inputKey, $record)
                    : $this->updateRecord($projectSite, $relationMethod, $inputKey, $record);

                if ($nestedStaffRecords !== null) {
                    $this->syncInfrastructureStaff($item, $nestedStaff['relation'], $nestedStaffRecords);
                }
            }
        }

        $this->syncBudgetUtilization($projectSite, $request);
        $this->syncLegalDocuments($projectSite, $request);
    }

    /**
     * Whether a submitted record carries nothing at all.
     *
     * The CBIN and CCDP forms seed every repeating section with one blank row so
     * there is something to type into, and that row has no id. Treated as a new
     * record it became a real one: changing a project's type of contract
     * silently inserted an empty equipment row, an empty training record, an empty
     * budget row and an empty marketing infrastructure, on every save of a project
     * that did not already have them.
     *
     * Blank means null, an empty string, false, or a numeric zero - which is
     * exactly what those template rows hold. Anything else is a value somebody
     * typed.
     *
     * A nested array is blank when it holds nothing but blank rows, so an
     * infrastructure whose only content is its own blank staff template is still
     * recognised as untouched. It recurses because the nested groups go two deep.
     *
     * @param  array<array-key, mixed>  $record
     */
    private function isBlank(array $record): bool
    {
        foreach ($record as $value) {
            if (is_array($value)) {
                if (! $this->isBlank($value)) {
                    return false;
                }

                continue;
            }

            if (in_array($value, [null, '', false], true)) {
                continue;
            }

            if (is_numeric($value) && (float) $value === 0.0) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * Defends against malformed input: a non-array payload for the relation
     * key, or a non-array individual row (e.g. a stray string/null in the
     * list), is dropped rather than throwing a TypeError deep in Eloquent.
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRecords(mixed $records): array
    {
        if (! is_array($records)) {
            return [];
        }

        return array_values(array_filter($records, static fn ($record) => is_array($record)));
    }

    private function updateRecord(ProjectSite $projectSite, string $relationMethod, string $inputKey, array $record): Model
    {
        $item = $projectSite->{$relationMethod}()->find($record['id']);
        if (! $item) {
            return $this->createRecord($projectSite, $relationMethod, $inputKey, $record);
        }

        // Strip 'id' up front so it can never be mass-assigned below,
        // regardless of which branch runs — an update payload must never
        // be able to overwrite the primary key.
        $record = collect($record)->except('id')->all();

        if (! in_array($inputKey, self::RELATIONS_WITH_ADDRESS, true)) {
            $record = $this->addresses->withoutAddress($record);
            $item->update($record);

            return $item;
        }

        $addressFields = $this->addresses->extract($record);
        $itemData = $this->addresses->withoutAddress($record);

        $item->update($itemData);
        $this->addresses->sync($item, $addressFields);

        if ($inputKey === 'marketing_infrastructure') {
            $this->maybeSyncDairyBoxAddress($record, $addressFields);
        }

        return $item;
    }

    private function createRecord(ProjectSite $projectSite, string $relationMethod, string $inputKey, array $record): Model
    {
        // 'id' is never expected here (createRecord is only reached for
        // records with no truthy 'id'), but stripped defensively in case
        // the frontend ever sends id: null or id: ''.
        $record = collect($record)->except('id')->all();

        if (! in_array($inputKey, self::RELATIONS_WITH_ADDRESS, true)) {
            $record = $this->addresses->withoutAddress($record);

            return $projectSite->{$relationMethod}()->create($record);
        }

        $addressFields = $this->addresses->extract($record);
        $itemData = $this->addresses->withoutAddress($record);

        $item = $projectSite->{$relationMethod}()->create($itemData);
        $this->addresses->create($item, $addressFields);

        if ($inputKey === 'marketing_infrastructure') {
            $this->maybeSyncDairyBoxAddress($record, $addressFields);
        }

        return $item;
    }

    /**
     * Persist the replace-on-save staff collection for one infrastructure.
     * A missing nested key leaves that infrastructure's staff untouched;
     * an explicitly empty array clears them.
     */
    private function syncInfrastructureStaff(Model $infrastructure, string $relationMethod, mixed $records): void
    {
        $records = $this->normalizeRecords($records);
        // Earlier saves could insert the same staff row again when the relation
        // query reused its delete constraint. Collapse those identical rows on
        // the next edit so the already-created duplicates are removed too.
        $records = collect($records)
            ->unique(fn (array $record) => json_encode(collect($record)->except('id')->sortKeys()->all()))
            ->values()
            ->all();
        $existingIds = array_values(array_filter(
            array_column($records, 'id'),
            static fn ($id) => $id !== null && $id !== ''
        ));

        $staffRelation = $infrastructure->{$relationMethod}();

        $existingIds !== []
            ? $staffRelation->whereNotIn('id', $existingIds)->delete()
            : $staffRelation->delete();

        foreach ($records as $record) {
            // The relation used for the delete above may still carry its
            // whereNotIn constraint. Use a fresh relation to look up submitted
            // rows, otherwise existing staff are missed and re-created on each
            // save.
            $item = empty($record['id'])
                ? null
                : $infrastructure->{$relationMethod}()->find($record['id']);
            $data = collect($record)->except('id')->all();

            if ($item) {
                $item->update($data);

                continue;
            }

            if ($this->isBlank($data)) {
                continue;
            }

            $infrastructure->{$relationMethod}()->create($data);
        }
    }

    /**
     * When the frontend toggled "copy processing address" for a marketing
     * infrastructure row, also update the linked DairyBox's own address record
     * so the change persists on the DairyBox side.
     *
     * `$record` is the raw input row (before address fields were stripped),
     * still containing `update_dairy_box_address` and `dairy_box_id`.
     * `$addressFields` are the already-extracted address key/value pairs.
     */
    private function maybeSyncDairyBoxAddress(array $record, array $addressFields): void
    {
        if (empty($record['update_dairy_box_address'])) {
            return;
        }

        $dairyBoxId = empty($record['dairy_box_id']) ? null : (int) $record['dairy_box_id'];
        if (! $dairyBoxId || array_filter($addressFields) === []) {
            return;
        }

        $dairyBox = DairyBox::with('address')->find($dairyBoxId);
        if ($dairyBox) {
            $this->addresses->sync($dairyBox, $addressFields);
        }
    }

    private function syncBudgetUtilization(ProjectSite $projectSite, Request $request): void
    {
        if (! $request->has('budget_utilization')) {
            return;
        }

        $budgetUtilization = $request->input('budget_utilization');

        $projectSite->budgetUtilization()->delete();

        if (is_array($budgetUtilization) && $budgetUtilization !== [] && ! $this->isBlank($budgetUtilization)) {
            $projectSite->budgetUtilization()->create($budgetUtilization);
        }
    }

    private function syncLegalDocuments(ProjectSite $projectSite, Request $request): void
    {
        $docTypes = ['moa', 'dod_usufruct', 'management_agreement_contract', 'dairy_buffalo_dispersal_agreement'];

        // Load existing documents indexed by type so we can preserve or replace them
        $existing = $projectSite->documents()
            ->whereIn('type', $docTypes)
            ->get()
            ->keyBy('type');

        foreach ($docTypes as $type) {
            $fileKey = "legal_documents.{$type}";

            if (! $request->hasFile($fileKey)) {
                // No new file uploaded — leave the existing record untouched
                continue;
            }

            $uploadedFile = $request->file($fileKey);

            if (! $uploadedFile || ! $uploadedFile->isValid()) {
                continue;
            }

            // Delete the old file from storage if one exists
            if ($existing->has($type) && $existing[$type]->attachment) {
                Storage::disk('media')->delete($existing[$type]->attachment);
            }

            $path = $uploadedFile->store("project-site-documents/{$type}", 'media');

            if ($existing->has($type)) {
                $existing[$type]->update([
                    'attachment' => $path,
                    'description' => $uploadedFile->getClientOriginalName(),
                ]);
            } else {
                $projectSite->documents()->create([
                    'type' => $type,
                    'doc_type' => $type,
                    'attachment' => $path,
                    'description' => $uploadedFile->getClientOriginalName(),
                ]);
            }
        }
    }
}
