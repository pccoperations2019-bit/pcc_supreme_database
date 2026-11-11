<?php

namespace Tests\Feature\Documents;

use App\Enums\DocumentStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Agency;
use App\Models\Center;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 5: the review decision moved from PCC-level to center level.
 *
 * A center's reviewer approves or rejects its own center's documents; a PCC
 * administrator reviews only after center approval. The reviewer must never gain the
 * uploader's powers, which is the failure mode when a new role is added to a
 * controller that only knew about two.
 */
class CenterReviewerTest extends TestCase
{
    use RefreshDatabase;

    private Center $myCenter;

    private Center $otherCenter;

    private User $encoder;

    private User $otherEncoder;

    private User $reviewer;

    private User $otherReviewer;

    private User $pccAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (config('roles.permissions') as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (config('roles.roles') as $name => $permissions) {
            Role::findOrCreate($name, 'web')->syncPermissions($permissions);
        }

        Storage::fake('dpm');

        $agency = Agency::create(['code' => 'PCC', 'name' => 'Philippine Carabao Center']);

        $this->myCenter = Center::create(['agency_id' => $agency->id, 'code' => 'MINE', 'name' => 'Mine']);
        $this->otherCenter = Center::create(['agency_id' => $agency->id, 'code' => 'THEIRS', 'name' => 'Theirs']);

        $this->encoder = $this->user('encoder', $agency, $this->myCenter, UserRoleEnum::CENTER_ADMIN);
        $this->otherEncoder = $this->user('other-encoder', $agency, $this->otherCenter, UserRoleEnum::CENTER_ADMIN);
        $this->reviewer = $this->user('reviewer', $agency, $this->myCenter, UserRoleEnum::REVIEWER);
        $this->otherReviewer = $this->user('other-reviewer', $agency, $this->otherCenter, UserRoleEnum::REVIEWER);
        $this->pccAdmin = $this->user('pcc', $agency, null, UserRoleEnum::AGENCY_ADMIN);
    }

    private function user(string $name, Agency $agency, ?Center $center, UserRoleEnum $role): User
    {
        $user = User::factory()->create([
            'username' => $name.'-'.Str::random(5),
            'agency_id' => $agency->id,
            'center_id' => $center?->id,
        ]);
        $user->assignRole($role->value);

        return $user->fresh();
    }

    /**
     * A document belonging to $center, uploaded by the encoder through the real
     * endpoint and therefore already awaiting review and tagged to that center.
     *
     * Built through the HTTP endpoint rather than by inserting rows, so the
     * center tagging under test is the one the controller actually applies.
     */
    private function pendingDocument(Center $center, string $code): Document
    {
        $key = 'files/'.fake()->uuid()."/{$code}-document.pdf";
        Storage::disk('dpm')->put($key, 'pdf-bytes');

        // Uploading as that center's own encoder: the controller rewrites the
        // submitted center tag to the uploader's center, so only the center's
        // encoder can produce a document belonging to it.
        $uploader = $center->is($this->myCenter) ? $this->encoder : $this->otherEncoder;

        $this->actingAs($uploader)
            ->post(route('documents.store'), [
                'title' => "{$code} document",
                'file_name' => "{$code}.pdf",
                'file_key' => $key,
                'file_type' => 'application/pdf',
                'file_size' => 9,
                'tags' => [
                    ['key' => 'document', 'value' => 'business_registration'],
                    ['key' => 'center', 'value' => $center->code],
                ],
            ])
            ->assertSessionHasNoErrors();

        return Document::query()->where('title', "{$code} document")->firstOrFail();
    }

    public function test_a_reviewer_can_approve_their_own_centers_document(): void
    {
        $document = $this->pendingDocument($this->myCenter, 'MINE');

        $this->actingAs($this->reviewer)
            ->post("/documents/{$document->id}/approve")
            ->assertRedirect();

        $document->refresh();

        $this->assertSame(DocumentStatusEnum::PENDING_AGENCY_REVIEW, $document->status);
        $this->assertSame($this->reviewer->id, $document->center_reviewed_by);
        $this->assertSame($this->reviewer->id, $document->reviewed_by);
        $this->assertNotNull($document->reviewed_at);
    }

    public function test_a_reviewer_can_reject_their_own_centers_document(): void
    {
        $document = $this->pendingDocument($this->myCenter, 'MINE');

        $this->actingAs($this->reviewer)
            ->post("/documents/{$document->id}/reject", ['rejection_reason' => 'Illegible scan'])
            ->assertRedirect();

        $document->refresh();

        $this->assertSame(DocumentStatusEnum::REJECTED_BY_CENTER, $document->status);
        $this->assertSame('Illegible scan', $document->rejection_reason);
    }

    public function test_a_reviewer_cannot_review_another_centers_document(): void
    {
        $document = $this->pendingDocument($this->otherCenter, 'THEIRS');

        $this->actingAs($this->reviewer)
            ->post("/documents/{$document->id}/approve")
            ->assertForbidden();

        $this->assertSame(DocumentStatusEnum::PENDING_CENTER_REVIEW, $document->fresh()->status);
    }

    public function test_a_reviewer_cannot_reject_another_centers_document(): void
    {
        $document = $this->pendingDocument($this->otherCenter, 'THEIRS');

        $this->actingAs($this->reviewer)
            ->post("/documents/{$document->id}/reject", ['rejection_reason' => 'nope'])
            ->assertForbidden();
    }

    public function test_a_pcc_administrator_cannot_skip_center_review(): void
    {
        $document = $this->pendingDocument($this->otherCenter, 'THEIRS');

        $this->actingAs($this->pccAdmin)
            ->post("/documents/{$document->id}/approve")
            ->assertForbidden();

        $this->assertSame(DocumentStatusEnum::PENDING_CENTER_REVIEW, $document->fresh()->status);
    }

    public function test_a_reviewer_cannot_edit_a_document(): void
    {
        // The dangerous inheritance: authorizeDocumentAction used to return early
        // for anyone who was not a center admin.
        $document = $this->pendingDocument($this->myCenter, 'MINE');

        $this->actingAs($this->reviewer)
            ->get("/documents/{$document->id}/edit")
            ->assertForbidden();

        $this->actingAs($this->reviewer)
            ->put("/documents/{$document->id}", ['title' => 'Rewritten'])
            ->assertForbidden();
    }

    public function test_a_reviewer_cannot_submit_a_document(): void
    {
        $document = $this->pendingDocument($this->myCenter, 'MINE');

        $this->actingAs($this->reviewer)
            ->post("/documents/{$document->id}/submit")
            ->assertForbidden();
    }

    public function test_a_reviewer_cannot_upload_a_document(): void
    {
        $this->actingAs($this->reviewer)
            ->post('/documents', [])
            ->assertForbidden();
    }

    public function test_a_reviewer_cannot_delete_a_document(): void
    {
        $document = $this->pendingDocument($this->myCenter, 'MINE');

        $this->actingAs($this->reviewer)
            ->delete("/documents/{$document->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('documents', ['id' => $document->id]);
    }

    public function test_an_encoder_still_cannot_review(): void
    {
        $document = $this->pendingDocument($this->myCenter, 'MINE');

        $this->actingAs($this->encoder)
            ->post("/documents/{$document->id}/approve")
            ->assertForbidden();

        $permissions = $this->actingAs($this->encoder)
            ->get('/documents')
            ->assertOk()
            ->viewData('page')['props']['documentPermissions'];

        $this->assertFalse($permissions['can_review']);
    }

    public function test_a_reviewer_only_sees_their_own_centers_documents(): void
    {
        $mine = $this->pendingDocument($this->myCenter, 'MINE');
        $theirs = $this->pendingDocument($this->otherCenter, 'THEIRS');

        $props = $this->actingAs($this->reviewer)
            ->get('/documents')
            ->assertOk()
            ->assertSee($mine->title)
            ->assertDontSee($theirs->title)
            ->viewData('page')['props'];

        $this->assertTrue($props['documentPermissions']['can_review']);
        $this->assertSame([$mine->id], array_column($props['documents']['data'], 'id'));
    }

    public function test_a_reviewer_cannot_open_another_centers_document(): void
    {
        $theirs = $this->pendingDocument($this->otherCenter, 'THEIRS');

        $this->actingAs($this->reviewer)
            ->get("/documents/{$theirs->id}")
            ->assertNotFound();
    }

    public function test_the_show_page_reports_review_rights_for_its_own_center(): void
    {
        $mine = $this->pendingDocument($this->myCenter, 'MINE');
        $theirs = $this->pendingDocument($this->otherCenter, 'THEIRS');

        $props = $this->actingAs($this->reviewer)
            ->get("/documents/{$mine->id}")
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertTrue($props['documentPermissions']['can_review']);

        $this->actingAs($this->reviewer)
            ->get("/documents/{$theirs->id}")
            ->assertNotFound();
    }
}
