<?php

namespace Tests\Feature\Documents;

use App\Concerns\HasQueueMetadata;
use App\Enums\DocumentStatusEnum;
use App\Enums\UserRoleEnum;
use App\Events\EntityTagged;
use App\Listeners\HandleEntityTagged;
use App\Models\Agency;
use App\Models\Center;
use App\Models\Document;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Documents uploaded by a center admin wait for a PCC admin decision, and only
 * an approved document may enter the import workflows.
 */
class DocumentReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Center $center;

    private User $centerAdmin;

    private User $pccAdmin;

    private User $centerReviewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesPermissionsSeeder::class);

        Role::findOrCreate(UserRoleEnum::CENTER_ADMIN->value, 'web');
        Role::findOrCreate(UserRoleEnum::AGENCY_ADMIN->value, 'web');
        Role::findOrCreate(UserRoleEnum::REVIEWER->value, 'web');

        Storage::fake('dpm');

        $agency = Agency::create(['code' => 'PCC', 'name' => 'Philippine Carabao Center']);
        $this->center = Center::create([
            'agency_id' => $agency->id,
            'code' => 'CLSU',
            'name' => 'CLSU Center',
        ]);

        $this->centerAdmin = $this->user('admin-clsu', $agency->id, $this->center->id, UserRoleEnum::CENTER_ADMIN->value);
        $this->pccAdmin = $this->user('pcc-admin', $agency->id, null, UserRoleEnum::AGENCY_ADMIN->value);
        $this->centerReviewer = $this->user('reviewer-clsu', $agency->id, $this->center->id, UserRoleEnum::REVIEWER->value);
    }

    public function test_a_center_admin_upload_goes_straight_to_pending_center_review(): void
    {
        $this->upload();

        $document = Document::query()->firstOrFail();

        $this->assertSame(DocumentStatusEnum::PENDING_CENTER_REVIEW, $document->status);
        $this->assertNotNull($document->submitted_at, 'The upload is submitted as part of the upload.');
    }

    public function test_a_pcc_admin_upload_stays_a_draft_until_it_is_submitted(): void
    {
        $key = 'files/'.fake()->uuid().'/Template - CBIN:CCDP.xlsx';
        Storage::disk('dpm')->put($key, 'spreadsheet-bytes');

        // Only a center admin's upload is submitted automatically; a PCC admin's
        // own upload stays editable as a draft.
        $this->actingAs($this->pccAdmin)
            ->post(route('documents.store'), [
                'title' => 'CBIN/CCDP Project Sites',
                'file_name' => 'Template - CBIN:CCDP.xlsx',
                'file_key' => $key,
                'file_type' => 'application/vnd.ms-excel',
                'file_size' => 16,
                'tags' => [
                    ['key' => 'template', 'value' => 'cbin_ccdp_master_list'],
                    ['key' => 'center', 'value' => 'CLSU'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $document = Document::query()->where('title', 'CBIN/CCDP Project Sites')->firstOrFail();

        $this->assertSame(DocumentStatusEnum::DRAFT, $document->status);
        $this->assertNull($document->submitted_at);
    }

    public function test_a_pcc_admin_can_submit_and_approve_an_agency_level_upload_without_a_center(): void
    {
        $document = $this->agencyLevelUpload();

        // No center stage exists, so the upload is submitted for agency review
        // immediately instead of waiting as a draft.
        $this->assertSame(DocumentStatusEnum::PENDING_AGENCY_REVIEW, $document->status);

        // A center-less document must still be visible to its agency's staff.
        $this->actingAs($this->pccAdmin)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Documents/Index')
                ->has('documents.data', 1)
                ->where('documents.data.0.id', $document->id));

        Event::fake([EntityTagged::class]);

        $this->actingAs($this->pccAdmin)
            ->post(route('documents.approve', $document))
            ->assertSessionHas('success');

        // The listener must see an agency-only approval as final, otherwise it
        // bails before processing and the row stays Approved forever.
        $approved = $document->fresh();
        $this->assertSame(DocumentStatusEnum::APPROVED, $approved->status);
        $this->assertTrue($approved->hasFinalApprovalForCurrentSubmission());
    }

    public function test_an_agency_level_approval_starts_processing(): void
    {
        $document = $this->agencyLevelUpload();

        // Same path as the center test: the listener claims the approval and
        // hands the file to the workflow, which then records the failure.
        Storage::disk('dpm')->delete($document->file->path);

        $this->actingAs($this->pccAdmin)
            ->from(route('documents.index'))
            ->post(route('documents.approve', $document));

        $document->refresh();

        $this->assertSame(DocumentStatusEnum::FAILED, $document->status);
        $this->assertStringContainsString(
            'missing from storage',
            (string) $document->metadata['queue_import']['error']
        );
    }

    public function test_a_center_admin_upload_records_the_submission_it_triggers(): void
    {
        $document = $this->upload();

        $submission = Activity::forSubject($document)
            ->where('event', 'document-submitted-for-review')
            ->sole();

        $this->assertTrue($submission->causer->is($this->centerAdmin));
        $this->assertSame('draft', $submission->properties['old_status']);
        $this->assertSame('pending_center_review', $submission->properties['new_status']);
    }

    public function test_tagging_an_awaiting_review_document_does_not_start_processing(): void
    {
        $document = $this->upload();

        // Replay the tag event the way the listener would receive it.
        (new HandleEntityTagged)->handle(new EntityTagged(
            tagKey: 'template',
            tagValue: 'cbin_ccdp_master_list',
            taggableType: 'document',
            taggableId: $document->id,
        ));

        $this->assertSame(
            DocumentStatusEnum::PENDING_CENTER_REVIEW,
            $document->fresh()->status,
            'A document awaiting review must not be handed to an import workflow.'
        );
    }

    public function test_a_center_admin_can_submit_a_draft_for_review(): void
    {
        // A draft that still needs a manual submit - e.g. one uploaded by a PCC
        // admin, who does not auto-submit.
        $document = $this->draft();

        $this->actingAs($this->centerAdmin)
            ->from(route('documents.index'))
            ->post(route('documents.submit', $document))
            ->assertRedirect(route('documents.index'));

        $document->refresh();

        $this->assertSame(DocumentStatusEnum::PENDING_CENTER_REVIEW, $document->status);
        $this->assertNotNull($document->submitted_at);
    }

    public function test_a_center_admin_cannot_approve_or_reject(): void
    {
        $document = $this->upload();

        $this->actingAs($this->centerAdmin)
            ->post(route('documents.approve', $document))
            ->assertForbidden();

        $this->actingAs($this->centerAdmin)
            ->post(route('documents.reject', $document), ['rejection_reason' => 'nope'])
            ->assertForbidden();

        $this->assertSame(DocumentStatusEnum::PENDING_CENTER_REVIEW, $document->fresh()->status);
    }

    public function test_center_approval_forwards_to_agency_without_dispatching_imports(): void
    {
        $document = $this->upload();
        Event::fake([EntityTagged::class]);

        $this->actingAs($this->centerReviewer)
            ->post(route('documents.approve', $document))
            ->assertSessionHas('success', 'Center review approved. The document is awaiting agency review.');

        $document->refresh();

        $this->assertSame(DocumentStatusEnum::PENDING_AGENCY_REVIEW, $document->status);
        $this->assertSame($this->centerReviewer->id, $document->center_reviewed_by);
        $this->assertNull($document->agency_reviewed_by);
        Event::assertNotDispatched(EntityTagged::class);
    }

    public function test_center_rejection_does_not_start_processing(): void
    {
        $document = $this->upload();
        Event::fake([EntityTagged::class]);

        $this->actingAs($this->centerReviewer)
            ->post(route('documents.reject', $document), ['rejection_reason' => 'Incomplete center form.'])
            ->assertSessionHas('success');

        $this->assertSame(DocumentStatusEnum::REJECTED_BY_CENTER, $document->fresh()->status);
        $this->assertSame('Incomplete center form.', $document->fresh()->rejection_reason);
        Event::assertNotDispatched(EntityTagged::class);
    }

    public function test_approving_records_the_reviewer_and_starts_processing(): void
    {
        Event::fake([EntityTagged::class]);

        $document = $this->pendingReview();

        $this->actingAs($this->pccAdmin)
            ->from(route('documents.index'))
            ->post(route('documents.approve', $document))
            ->assertRedirect(route('documents.index'));

        $document->refresh();

        $this->assertSame(DocumentStatusEnum::APPROVED, $document->status);
        $this->assertSame($this->pccAdmin->id, $document->reviewed_by);
        $this->assertNotNull($document->reviewed_at);

        // Approval is the controlled entry point into processing.
        Event::assertDispatched(EntityTagged::class);
    }

    public function test_a_document_can_only_be_approved_once(): void
    {
        Event::fake([EntityTagged::class]);

        $document = $this->pendingReview();

        $this->actingAs($this->pccAdmin)->post(route('documents.approve', $document));

        $this->actingAs($this->pccAdmin)
            ->from(route('documents.index'))
            ->post(route('documents.approve', $document))
            ->assertSessionHas('error');

        $this->assertSame(
            1,
            Document::query()->where('status', DocumentStatusEnum::APPROVED->value)->count()
        );

        $this->assertSame(
            1,
            Activity::forSubject($document)->where('event', 'agency-review-approved')->count(),
            'A second approval must not record another approval.'
        );
    }

    public function test_the_activity_log_records_the_review_trail_with_the_causer(): void
    {
        $document = $this->pendingReview();

        // Upload, automatic submission, and center approval are logged.
        $this->assertSame(
            ['Document Uploaded', 'Document Submitted for Center Review', 'Center Review Approved'],
            Activity::forSubject($document)->pluck('description')->all()
        );

        $this->actingAs($this->pccAdmin)->post(route('documents.reject', $document), [
            'rejection_reason' => 'Wrong reporting period.',
        ]);

        $activities = Activity::forSubject($document)->orderBy('id')->get();

        $this->assertSame(
            ['Document Uploaded', 'Document Submitted for Center Review', 'Center Review Approved', 'Agency Review Rejected'],
            $activities->pluck('description')->all()
        );

        $submitted = $activities->last();

        $this->assertTrue($submitted->causer->is($this->pccAdmin), 'Reviewer is attributed.');
        $this->assertSame('pending_agency_review', $submitted->properties['old_status']);
        $this->assertSame('rejected_by_agency', $submitted->properties['new_status']);
        $this->assertSame(
            'Wrong reporting period.',
            $submitted->properties['rejection_reason']
        );
    }

    public function test_the_document_list_exposes_the_reviewer_for_the_reviewed_by_column(): void
    {
        $document = $this->pendingReview();

        $this->actingAs($this->pccAdmin)->post(route('documents.approve', $document));

        $response = $this->actingAs($this->pccAdmin)
            ->get(route('documents.index'))
            ->assertOk();

        $row = collect($response->viewData('page')['props']['documents']['data'])
            ->firstWhere('id', $document->id);

        // The "Reviewed By" column reads reviewed_by, which Laravel snake-cases
        // from the reviewedBy relation and which therefore replaces the raw id.
        $this->assertSame($this->pccAdmin->name, $row['reviewed_by']['name']);
        $this->assertNotNull($row['reviewed_at']);
    }

    public function test_re_entering_processing_does_not_log_a_self_transition(): void
    {
        $document = $this->upload();

        // A workflow and its Excel import both call setQueueMetadata(); only the
        // call that really moves the document into processing is a transition.
        $queue = new class
        {
            use HasQueueMetadata;

            public function start(int $documentId): void
            {
                $this->setQueueMetadata($documentId, 'import');
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no valid final approval');
        $queue->start($document->id);
    }

    public function test_the_inertia_json_payload_carries_the_reviewer(): void
    {
        $document = $this->pendingReview();

        $this->actingAs($this->pccAdmin)->post(route('documents.approve', $document));

        // What the browser actually receives over the wire.
        $json = $this->actingAs($this->pccAdmin)
            ->get(route('documents.index'), ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->json('props.documents.data');

        $row = collect($json)->firstWhere('id', $document->id);

        $this->assertNotNull($row, 'approved document missing from the list payload');
        $this->assertSame(
            $this->pccAdmin->name,
            $row['reviewed_by']['name'] ?? null,
            'reviewed_by must survive Inertia JSON serialization'
        );
    }

    public function test_metadata_is_no_longer_used_for_transition_history(): void
    {
        $document = $this->pendingReview();

        $this->assertArrayNotHasKey('review', $document->fresh()->metadata ?? []);
    }

    public function test_rejecting_requires_a_reason_and_records_the_reviewer(): void
    {
        $document = $this->pendingReview();

        $this->actingAs($this->pccAdmin)
            ->post(route('documents.reject', $document))
            ->assertSessionHasErrors('rejection_reason');

        $this->actingAs($this->pccAdmin)
            ->from(route('documents.index'))
            ->post(route('documents.reject', $document), [
                'rejection_reason' => 'Wrong reporting period.',
            ])
            ->assertRedirect(route('documents.index'));

        $document->refresh();

        $this->assertSame(DocumentStatusEnum::REJECTED_BY_AGENCY, $document->status);
        $this->assertSame('Wrong reporting period.', $document->rejection_reason);
        $this->assertSame($this->pccAdmin->id, $document->reviewed_by);
    }

    public function test_a_rejected_document_does_not_start_processing(): void
    {
        $document = $this->pendingReview();

        // Fake only after the upload/submit, so we observe the reject's events.
        Event::fake([EntityTagged::class]);

        $this->actingAs($this->pccAdmin)->post(route('documents.reject', $document), [
            'rejection_reason' => 'Wrong reporting period.',
        ]);

        Event::assertNotDispatched(EntityTagged::class);
    }

    public function test_a_center_admin_can_edit_and_resubmit_a_rejected_document(): void
    {
        $document = $this->pendingReview();

        $this->actingAs($this->pccAdmin)->post(route('documents.reject', $document), [
            'rejection_reason' => 'Wrong reporting period.',
        ]);

        $this->actingAs($this->centerAdmin)
            ->get(route('documents.edit', $document))
            ->assertOk();

        $this->actingAs($this->centerAdmin)
            ->from(route('documents.index'))
            ->post(route('documents.submit', $document))
            ->assertRedirect(route('documents.index'));

        $document->refresh();

        $this->assertSame(DocumentStatusEnum::PENDING_CENTER_REVIEW, $document->status);
        $this->assertSame(2, (int) $document->submission_version);
        $this->assertNull($document->center_reviewed_by);
        $this->assertNull($document->agency_reviewed_by);
        $this->assertNull($document->rejection_reason, 'Resubmitting clears the previous rejection.');
        $this->assertSame(1, Activity::forSubject($document)->where('event', 'agency-review-rejected')->count());
    }

    public function test_a_center_admin_cannot_edit_a_document_that_is_awaiting_approval(): void
    {
        $document = $this->pendingReview();

        $this->actingAs($this->centerAdmin)
            ->get(route('documents.edit', $document))
            ->assertForbidden();

        $this->actingAs($this->centerAdmin)
            ->put(route('documents.update', $document), ['title' => 'Changed'])
            ->assertForbidden();
    }

    public function test_a_center_admin_cannot_delete_a_document(): void
    {
        $document = $this->upload();

        $this->actingAs($this->centerAdmin)
            ->delete(route('documents.destroy', $document))
            ->assertForbidden();
    }

    public function test_processing_fails_with_a_clear_reason_when_the_source_file_is_missing(): void
    {
        $document = $this->pendingReview();

        // File row exists, but nothing was actually written to the disk.
        Storage::disk('dpm')->delete($document->file->path);

        $this->actingAs($this->pccAdmin)
            ->from(route('documents.index'))
            ->post(route('documents.approve', $document));

        $document->refresh();

        $this->assertSame(DocumentStatusEnum::FAILED, $document->status);
        $this->assertStringContainsString('missing from storage', (string) $document->metadata['queue_import']['error']);
    }

    private function pendingReview(): Document
    {
        $document = $this->upload();
        $this->actingAs($this->centerReviewer)
            ->post(route('documents.approve', $document))
            ->assertSessionHas('success');

        return $document->fresh();
    }

    /**
     * A document that still sits on draft and needs a manual submit.
     */
    private function draft(): Document
    {
        $document = $this->upload();

        $document->update(['status' => DocumentStatusEnum::DRAFT, 'submitted_at' => null]);

        return $document->fresh();
    }

    private function upload(): Document
    {
        $key = 'files/'.fake()->uuid().'/Template - CBIN:CCDP.xlsx';
        Storage::disk('dpm')->put($key, 'spreadsheet-bytes');

        $this->actingAs($this->centerAdmin)
            ->post(route('documents.store'), [
                'title' => 'CBIN/CCDP Project Sites',
                'file_name' => 'Template - CBIN:CCDP.xlsx',
                'file_key' => $key,
                'file_type' => 'application/vnd.ms-excel',
                'file_size' => 16,
                'tags' => [
                    ['key' => 'template', 'value' => 'cbin_ccdp_master_list'],
                    ['key' => 'document', 'value' => 'business_registration'],
                    ['key' => 'center', 'value' => 'CLSU'],
                ],
            ])
            ->assertSessionHasNoErrors();

        return Document::query()->where('title', 'CBIN/CCDP Project Sites')->firstOrFail();
    }

    /**
     * A PCC-admin upload with no center or entity tag: an agency-level document.
     */
    private function agencyLevelUpload(): Document
    {
        $key = 'files/'.fake()->uuid().'/Template - Institutional Herd Master List.xlsx';
        Storage::disk('dpm')->put($key, 'spreadsheet-bytes');

        $this->actingAs($this->pccAdmin)
            ->post(route('documents.store'), [
                'title' => 'Agency Level Master List',
                'file_name' => 'Template - Institutional Herd Master List.xlsx',
                'file_key' => $key,
                'file_type' => 'application/vnd.ms-excel',
                'file_size' => 16,
                'tags' => [
                    ['key' => 'template', 'value' => 'institutional_herd_master_list'],
                ],
            ])
            ->assertSessionHasNoErrors();

        return Document::query()->where('title', 'Agency Level Master List')->firstOrFail();
    }

    private function user(string $username, int $agencyId, ?int $centerId, string $role): User
    {
        $user = User::factory()->create([
            'agency_id' => $agencyId,
            'center_id' => $centerId,
            'username' => $username,
            'password' => Hash::make('password'),
        ]);

        $user->assignRole($role);

        return $user;
    }
}
