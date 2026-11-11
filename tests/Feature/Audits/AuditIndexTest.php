<?php

namespace Tests\Feature\Audits;

use App\Enums\AuditActionEnum;
use App\Enums\AuditCategoryEnum;
use App\Enums\UserRoleEnum;
use App\Models\Agency;
use App\Models\Center;
use App\Models\Document;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\ActivityLogStatus;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Users\InteractsWithUsers;
use Tests\TestCase;

/**
 * The Audits page is the system-wide view over spatie/laravel-activitylog. It
 * must show the same rows the document review timeline shows, describe them in
 * language a PCC user can read, and stay behind the agency-admin role.
 */
class AuditIndexTest extends TestCase
{
    use InteractsWithUsers;
    use RefreshDatabase;

    private Center $center;

    private User $centerAdmin;

    private User $pccAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded from config rather than hand-listed: the route gates on
        // 'view-audits', so a role that stops holding it in config/roles.php must
        // stop reaching this page here too.
        $this->seedRolesAndPermissions();
        Storage::fake('dpm');

        // These tests are about how the page reads document activity, so the
        // fixtures must not become part of the log. The profiles log their own
        // edits now, so creating them here would otherwise put "Agency Created"
        // and "Center Created" in the middle of every listing these tests assert
        // on. Model-level logging is covered by ProfileAuditTrailTest.
        $logStatus = app(ActivityLogStatus::class);
        $logStatus->disable();

        $agency = Agency::create(['code' => 'PCC', 'name' => 'Philippine Carabao Center']);
        $this->center = Center::create([
            'agency_id' => $agency->id,
            'code' => 'CLSU',
            'name' => 'CLSU Center',
        ]);

        $this->centerAdmin = $this->user('admin-clsu', $agency->id, $this->center->id, UserRoleEnum::CENTER_ADMIN->value);
        $this->pccAdmin = $this->user('pcc-admin', $agency->id, null, UserRoleEnum::AGENCY_ADMIN->value);

        $logStatus->enable();
    }

    public function test_a_center_admin_sees_only_its_own_centers_activity(): void
    {
        // The center admin reaches the same page, pinned to its own center: its own
        // center's activity shows up, another center's does not.
        $other = Center::create([
            'agency_id' => $this->center->agency_id,
            'code' => 'NIZ',
            'name' => 'NIZ Center',
        ]);

        $this->seedActivityFor($this->center, 'CLSU Activity');
        $this->seedActivityFor($other, 'NIZ Activity');

        $json = $this->auditsJsonFor($this->centerAdmin);

        $this->assertContains('CLSU Activity', array_column($json, 'activity'));
        $this->assertNotContains('NIZ Activity', array_column($json, 'activity'));
    }

    public function test_a_center_admin_cannot_widen_its_scope_with_the_center_filter(): void
    {
        // The route and the query both have to hold: a hand-crafted filter naming
        // another center must narrow to nothing, not switch the scope over.
        $other = Center::create([
            'agency_id' => $this->center->agency_id,
            'code' => 'NIZ',
            'name' => 'NIZ Center',
        ]);

        $this->seedActivityFor($other, 'NIZ Activity');

        $json = $this->auditsJsonFor($this->centerAdmin, ['filter' => ['center' => $other->id]]);

        $this->assertSame([], array_column($json, 'activity'));
    }

    public function test_a_center_admin_is_only_offered_its_own_center_and_accounts(): void
    {
        $props = $this->actingAs($this->centerAdmin)
            ->get(route('audits.index'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame([], $props['filters']['centers'], 'The center filter is redundant when scoped.');
        $this->assertSame(
            [$this->centerAdmin->id],
            array_column($props['filters']['users'], 'id'),
        );
    }

    public function test_an_executive_reads_the_whole_agency_log(): void
    {
        // The Executive is the PCC-wide read-only role: it sees every center's
        // activity, exactly like the PCC Administrator, and the page has no write
        // side to be refused on.
        $other = Center::create([
            'agency_id' => $this->center->agency_id,
            'code' => 'NIZ',
            'name' => 'NIZ Center',
        ]);

        $this->seedActivityFor($other, 'NIZ Activity');
        $this->seedActivityFor($this->center, 'CLSU Activity');

        $executive = $this->user('executive', $this->center->agency_id, null, UserRoleEnum::EXECUTIVE->value);

        $activities = array_column($this->auditsJsonFor($executive), 'activity');

        $this->assertContains('NIZ Activity', $activities);
        $this->assertContains('CLSU Activity', $activities);
    }

    public function test_a_guest_cannot_reach_the_audits_page(): void
    {
        $this->get(route('audits.index'))->assertRedirect(route('login'));
    }

    public function test_a_reviewer_reads_its_own_centers_log(): void
    {
        // The reviewer is the oversight role, so it holds 'view-audits'. It is
        // center-scoped like the center admin, so it must reach the page pinned to
        // its own center rather than the whole agency.
        $other = Center::create([
            'agency_id' => $this->center->agency_id,
            'code' => 'NIZ',
            'name' => 'NIZ Center',
        ]);

        $this->seedActivityFor($this->center, 'CLSU Activity');
        $this->seedActivityFor($other, 'NIZ Activity');

        $reviewer = $this->user('reviewer-clsu', $this->center->agency_id, $this->center->id, UserRoleEnum::REVIEWER->value);

        $json = $this->auditsJsonFor($reviewer);

        $this->assertContains('CLSU Activity', array_column($json, 'activity'));
        $this->assertNotContains('NIZ Activity', array_column($json, 'activity'));
    }

    public function test_a_reviewer_cannot_widen_its_scope_with_the_center_filter(): void
    {
        $other = Center::create([
            'agency_id' => $this->center->agency_id,
            'code' => 'NIZ',
            'name' => 'NIZ Center',
        ]);

        $this->seedActivityFor($other, 'NIZ Activity');

        $reviewer = $this->user('reviewer-clsu', $this->center->agency_id, $this->center->id, UserRoleEnum::REVIEWER->value);

        $json = $this->auditsJsonFor($reviewer, ['filter' => ['center' => $other->id]]);

        $this->assertSame([], array_column($json, 'activity'));
    }

    public function test_an_encoder_cannot_reach_the_audits_page(): void
    {
        // The encoder enters data and never reviews it, so the audit log stays a
        // supervisory page. This is the counterpart to the nav gating: the link is
        // hidden for the encoder, and the route refuses it as well.
        $encoder = $this->user('encoder-clsu', $this->center->agency_id, $this->center->id, UserRoleEnum::ENCODER->value);

        $this->actingAs($encoder)->get(route('audits.index'))->assertForbidden();
    }

    public function test_an_agency_wide_reviewer_reads_the_whole_agency_log(): void
    {
        // A reviewer may be provisioned agency-wide, and a center-scoped role with
        // no center is not confined to anything - the same rule the rest of the app
        // applies. So it reads every center, exactly like the Executive.
        $other = Center::create([
            'agency_id' => $this->center->agency_id,
            'code' => 'NIZ',
            'name' => 'NIZ Center',
        ]);

        $this->seedActivityFor($other, 'NIZ Activity');
        $this->seedActivityFor($this->center, 'CLSU Activity');

        $reviewer = $this->user('reviewer-agency', $this->center->agency_id, null, UserRoleEnum::REVIEWER->value);

        $activities = array_column($this->auditsJsonFor($reviewer), 'activity');

        $this->assertContains('NIZ Activity', $activities);
        $this->assertContains('CLSU Activity', $activities);
    }

    public function test_a_pcc_admin_sees_document_approval_in_readable_language(): void
    {
        $document = $this->pendingReview();

        $this->actingAs($this->pccAdmin)->post(route('documents.approve', $document));

        $row = $this->firstRow(['activity' => 'Document Approved']);

        $this->assertSame($this->pccAdmin->name, $row['user']['name']);
        $this->assertSame('Agency Admin', $row['user']['role']);
        $this->assertSame('Document', $row['subject']['type']);
        $this->assertSame($document->title, $row['subject']['label']);
        $this->assertSame(route('documents.show', $document->id), $row['subject']['url']);
        $this->assertNotNull($row['occurred_at']);
    }

    public function test_the_row_describes_the_status_change_without_exposing_a_slug(): void
    {
        $document = $this->pendingReview();

        $this->actingAs($this->pccAdmin)->post(route('documents.approve', $document));

        $row = $this->firstRow(['activity' => 'Document Approved']);

        $this->assertSame(
            [['label' => 'Status', 'from' => 'Pending Review', 'to' => 'Approved']],
            $row['changes'],
        );

        // The internal event name never reaches the page.
        $this->assertStringNotContainsString('document-approved', json_encode($row));
        $this->assertStringNotContainsString('pending_review', json_encode($row));
    }

    public function test_a_rejection_surfaces_its_reason_as_readable_text(): void
    {
        $document = $this->pendingReview();

        $this->actingAs($this->pccAdmin)->post(route('documents.reject', $document), [
            'rejection_reason' => 'The submitted file contains incomplete records.',
        ]);

        $row = $this->firstRow(['activity' => 'Document Rejected']);

        $this->assertSame(
            [['label' => 'Status', 'from' => 'Pending Review', 'to' => 'Rejected']],
            $row['changes'],
        );
        $this->assertSame(
            [['label' => 'Reason', 'value' => 'The submitted file contains incomplete records.']],
            $row['notes'],
        );
    }

    public function test_a_queue_driven_transition_is_attributed_to_the_system(): void
    {
        $document = $this->pendingReview();

        // Processing runs on the queue, where the row is written with no causer
        // at all - that is what a system-attributed activity looks like.
        Activity::query()->create([
            'log_name' => 'default',
            'description' => 'Document Processing Failed',
            'event' => 'document-processing-failed',
            'subject_type' => $document->getMorphClass(),
            'subject_id' => $document->id,
            'properties' => collect([
                'old_status' => 'approved',
                'new_status' => 'failed',
                'error' => 'Source file is missing from storage.',
            ]),
        ]);

        $row = $this->firstRow(['activity' => 'Document Processing Failed']);

        $this->assertNull($row['user'], 'A queue transition must not invent a user.');
        $this->assertSame('Failed', AuditActionEnum::from($row['action'])->label());
        $this->assertSame(
            [['label' => 'Error', 'value' => 'Source file is missing from storage.']],
            $row['notes'],
        );
    }

    public function test_the_organization_is_the_center_the_document_belongs_to(): void
    {
        $document = $this->pendingReview();

        // The reviewer is a PCC officer with no center, so the only place the
        // center can come from is the document itself.
        $this->actingAs($this->pccAdmin)->post(route('documents.approve', $document));

        $row = $this->firstRow(['activity' => 'Document Approved']);

        $this->assertSame(['name' => 'CLSU Center', 'code' => 'CLSU'], $row['organization']);
    }

    public function test_a_partner_document_reports_the_center_of_its_partner(): void
    {
        // The partner's "Add Document" form tags a document with the partner and
        // nothing else, and the uploader may be a PCC officer who has no center
        // of their own. The partner sits in exactly one center, so that is where
        // the entry belongs.
        $partner = Partner::create([
            'center_id' => $this->center->id,
            'code' => 'PCC-KO',
            'name' => 'Kosi Milk Cooperative',
        ]);

        $document = $this->partnerDocument((string) $partner->id);

        $row = $this->firstRow(['activity' => 'Document Uploaded']);

        $this->assertSame('dole', $document->tags->firstWhere('key', 'document')->value);
        $this->assertSame(['name' => 'CLSU Center', 'code' => 'CLSU'], $row['organization']);
    }

    public function test_a_partner_document_tagged_by_code_also_reports_its_center(): void
    {
        // The same tag is written as a code from some forms and as an id from
        // others, so both have to resolve.
        $partner = Partner::create([
            'center_id' => $this->center->id,
            'code' => 'PCC-KO',
            'name' => 'Kosi Milk Cooperative',
        ]);

        $this->partnerDocument($partner->code);

        $row = $this->firstRow(['activity' => 'Document Uploaded']);

        $this->assertSame(['name' => 'CLSU Center', 'code' => 'CLSU'], $row['organization']);
    }

    public function test_activities_without_a_center_report_no_organization(): void
    {
        Activity::query()->create([
            'log_name' => 'default',
            'description' => 'Queue Import Finished',
            'event' => 'queue_import',
        ]);

        $row = $this->firstRow(['activity' => 'Queue Import Finished']);

        $this->assertNull($row['organization']);
        $this->assertNull($row['subject']);
        $this->assertNull($row['user']);
        $this->assertNull($row['action']);
        $this->assertSame(AuditCategoryEnum::SYSTEM->value, $row['category']);
    }

    public function test_an_event_without_a_description_still_reads_as_a_sentence(): void
    {
        Activity::query()->create([
            'log_name' => 'default',
            'description' => '',
            'event' => 'document-approved',
        ]);

        $row = $this->firstRow(['activity' => 'Document Approved']);

        $this->assertSame(AuditActionEnum::APPROVED->value, $row['action']);
    }

    public function test_the_page_defaults_to_the_newest_activity_first(): void
    {
        $this->seedActivity('Older Activity', now()->subDays(2));
        $this->seedActivity('Newer Activity', now()->subMinute());

        $json = $this->auditsJson();

        $this->assertSame(['Newer Activity', 'Older Activity'], array_column($json, 'activity'));
    }

    public function test_sorting_can_be_reversed_to_oldest_first(): void
    {
        $this->seedActivity('Older Activity', now()->subDays(2));
        $this->seedActivity('Newer Activity', now()->subMinute());

        $json = $this->auditsJson(['sort' => 'created_at']);

        $this->assertSame(['Older Activity', 'Newer Activity'], array_column($json, 'activity'));
    }

    public function test_the_category_filter_narrows_the_listing(): void
    {
        $this->pendingReview();
        $this->seedActivity('Queue Import Finished');

        $documents = $this->auditsJson(['filter' => ['category' => AuditCategoryEnum::DOCUMENTS->value]]);
        $system = $this->auditsJson(['filter' => ['category' => AuditCategoryEnum::SYSTEM->value]]);

        $this->assertNotEmpty($documents);
        $this->assertSame(
            ['Queue Import Finished'],
            array_column($system, 'activity'),
            'System is the fallback category, so it must exclude everything recognised.',
        );
    }

    public function test_the_action_filter_narrows_the_listing(): void
    {
        $this->pendingReview();
        $this->actingAs($this->pccAdmin)->post(route('documents.approve', Document::query()->firstOrFail()));

        $approved = $this->auditsJson(['filter' => ['action' => AuditActionEnum::APPROVED->value]]);
        $rejected = $this->auditsJson(['filter' => ['action' => AuditActionEnum::REJECTED->value]]);

        $this->assertSame(['Document Approved'], array_column($approved, 'activity'));
        $this->assertSame([], array_column($rejected, 'activity'));
    }

    public function test_the_user_filter_narrows_the_listing(): void
    {
        $this->pendingReview();
        $this->actingAs($this->pccAdmin)->post(route('documents.approve', Document::query()->firstOrFail()));

        $json = $this->auditsJson(['filter' => ['causer' => $this->centerAdmin->id]]);

        $this->assertNotEmpty($json);
        $this->assertSame(
            [$this->centerAdmin->name],
            array_values(array_unique(array_column(array_column($json, 'user'), 'name'))),
        );
    }

    public function test_the_center_filter_narrows_the_listing(): void
    {
        $this->pendingReview();
        $this->actingAs($this->pccAdmin)->post(route('documents.approve', Document::query()->firstOrFail()));
        $this->seedActivity('Queue Import Finished');

        $json = $this->auditsJson(['filter' => ['center' => $this->center->id]]);

        $this->assertNotContains('Queue Import Finished', array_column($json, 'activity'));
    }

    public function test_the_date_range_filter_narrows_the_listing(): void
    {
        $this->seedActivity('Long Ago Activity', now()->subMonths(6));

        $this->actingAs($this->pccAdmin)
            ->get(route('audits.index'))
            ->assertOk();

        $recent = $this->auditsJson(['filter' => ['from' => now()->subDays(1)->toDateString()]]);
        $old = $this->auditsJson(['filter' => ['to' => now()->subMonths(1)->toDateString()]]);

        $this->assertSame([], array_column($recent, 'activity'));
        $this->assertSame(['Long Ago Activity'], array_column($old, 'activity'));
    }

    public function test_search_matches_the_activity_and_the_actor(): void
    {
        $this->pendingReview();
        $this->actingAs($this->pccAdmin)->post(route('documents.approve', Document::query()->firstOrFail()));

        $this->assertSame(
            ['Document Approved'],
            array_column($this->auditsJson(['filter' => ['search' => 'Approved']]), 'activity'),
        );
        $this->assertContains(
            'Document Approved',
            array_column($this->auditsJson(['filter' => ['search' => $this->pccAdmin->name]]), 'activity'),
        );
    }

    public function test_the_page_shipped_the_filter_options_and_no_results_are_fine(): void
    {
        $props = $this->props();

        $this->assertSame(
            ['All Activities', 'Documents', 'Profiles', 'Goals', 'Products', 'Projects', 'Services', 'Production & Sales Data', 'Users', 'Reports', 'System'],
            array_merge(['All Activities'], array_column($props['filters']['categories'], 'label')),
        );
        $this->assertSame(
            ['All Actions', 'Created', 'Updated', 'Submitted', 'Approved', 'Rejected', 'Deleted', 'Processed', 'Failed'],
            array_merge(['All Actions'], array_column($props['filters']['actions'], 'label')),
        );
        $this->assertContains(
            ['id' => $this->centerAdmin->id, 'label' => $this->centerAdmin->name],
            $props['filters']['users'],
        );
        $this->assertContains(
            ['id' => $this->center->id, 'label' => 'PCC at CLSU'],
            collect($props['filters']['centers'])->map(fn (array $center) => [
                'id' => (int) $center['id'],
                'label' => $center['label'],
            ])->all(),
        );
    }

    public function test_an_empty_matching_set_still_renders(): void
    {
        $json = $this->auditsJson(['filter' => ['search' => 'nothing-matches-this']]);

        $this->assertSame([], $json);
    }

    private function auditsJson(array $query = []): array
    {
        return $this->props($query)['activities']['data'];
    }

    /**
     * @return array<string, mixed>
     */
    private function props(array $query = []): array
    {
        return $this->actingAs($this->pccAdmin)
            ->get(route('audits.index', $query))
            ->assertOk()
            ->viewData('page')['props'];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    private function auditsJsonFor(User $viewer, array $query = []): array
    {
        return $this->actingAs($viewer)
            ->get(route('audits.index', $query))
            ->assertOk()
            ->viewData('page')['props']['activities']['data'];
    }

    /**
     * An activity attributed to an account inside the given center, so the center
     * filter has a row of that center's own to match.
     */
    private function seedActivityFor(Center $center, string $description): Activity
    {
        $causer = $this->user('causer-'.str($center->code)->slug(), $center->agency_id, $center->id, UserRoleEnum::ENCODER->value);

        return Activity::query()->create([
            'log_name' => 'default',
            'description' => $description,
            'event' => str($description)->slug()->toString(),
            'causer_type' => $causer->getMorphClass(),
            'causer_id' => $causer->id,
            'properties' => collect(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $where
     * @return array<string, mixed>
     */
    private function firstRow(array $where): array
    {
        $rows = collect($this->auditsJson())->first(
            fn (array $row) => collect($where)->every(fn ($value, $key) => ($row[$key] ?? null) === $value)
        );

        $this->assertNotNull($rows, 'No audit row matched '.json_encode($where));

        return $rows;
    }

    private function seedActivity(string $description, $occurredAt = null): Activity
    {
        return Activity::query()->create([
            'log_name' => 'default',
            'description' => $description,
            'event' => str($description)->slug()->toString(),
            'properties' => collect(),
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);
    }

    /**
     * A document uploaded the way a partner's "Add Document" form does it:
     * tagged with the partner and its type, and with no center tag at all.
     */
    private function partnerDocument(string $partnerTagValue): Document
    {
        $key = 'files/'.fake()->uuid().'/DOLE.pdf';
        Storage::disk('dpm')->put($key, 'pdf-bytes');

        // Uploaded by a PCC officer, who has no center of their own - which is
        // exactly why the document has to resolve its own.
        $this->actingAs($this->pccAdmin)
            ->post(route('documents.store'), [
                'title' => 'DOLE',
                'file_name' => 'DOLE.pdf',
                'file_key' => $key,
                'file_type' => 'application/pdf',
                'file_size' => 9,
                'tags' => [
                    ['key' => 'partner', 'value' => $partnerTagValue],
                    ['key' => 'document', 'value' => 'dole'],
                ],
            ])
            ->assertSessionHasNoErrors();

        return Document::query()->where('title', 'DOLE')->firstOrFail();
    }

    /**
     * A document that has been uploaded and is waiting for review.
     */
    private function pendingReview(): Document
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
                    ['key' => 'center', 'value' => 'CLSU'],
                ],
            ])
            ->assertSessionHasNoErrors();

        return Document::query()->where('title', 'CBIN/CCDP Project Sites')->firstOrFail();
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
