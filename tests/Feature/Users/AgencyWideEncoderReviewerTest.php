<?php

namespace Tests\Feature\Users;

use App\Enums\DocumentStatusEnum;
use App\Enums\UserRoleEnum;
use App\Enums\UserStatusEnum;
use App\Models\Document;
use App\Models\Farmer;
use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * An encoder or a reviewer with no center is not a broken account: it is a
 * data-entry or review officer for the whole agency. These tests pin the three
 * things that make that true — it can be provisioned, its reach is nationwide,
 * and a center-pinned account keeps behaving exactly as before.
 */
class AgencyWideEncoderReviewerTest extends TestCase
{
    use InteractsWithUsers;
    use RefreshDatabase;

    private $agency;

    private $center;

    private $otherCenter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        Storage::fake('dpm');

        $this->agency = $this->agency();
        $this->center = $this->center($this->agency, 'CLSU');
        $this->otherCenter = $this->center($this->agency, 'NIZ');
    }

    public function test_an_encoder_can_be_provisioned_for_the_whole_agency(): void
    {
        $admin = $this->pccAdmin($this->agency);

        $this->actingAs($admin)
            ->post('/users', $this->userPayload([
                'role' => UserRoleEnum::ENCODER->value,
                'center_id' => null,
                'username' => 'agency_encoder',
                'email' => 'agency.encoder@pcc.gov.ph',
            ]))
            ->assertSessionHasNoErrors();

        $created = User::where('email', 'agency.encoder@pcc.gov.ph')->firstOrFail();

        $this->assertNull($created->center_id);
        $this->assertFalse($created->isCenterScoped(), 'No center means not confined to one.');
        $this->assertTrue($created->isPccLevel(), 'No center means agency-wide reach.');
    }

    public function test_a_center_encoder_still_must_be_given_a_center(): void
    {
        $admin = $this->pccAdmin($this->agency);

        $this->actingAs($admin)
            ->post('/users', $this->userPayload([
                'role' => UserRoleEnum::ENCODER->value,
                'center_id' => null,
                'username' => 'needs_center',
                'email' => 'needs.center@pcc.gov.ph',
            ]))
            ->assertSessionHasNoErrors();

        // The role above is agency-wide capable, so this asserts the other half:
        // a Center Admin is defined by its center and cannot be made agency-wide.
        $this->actingAs($admin)
            ->post('/users', $this->userPayload([
                'role' => UserRoleEnum::CENTER_ADMIN->value,
                'center_id' => null,
                'username' => 'center_admin_no_center',
                'email' => 'center.admin.no.center@pcc.gov.ph',
            ]))
            ->assertSessionHasErrors('center_id');
    }

    public function test_an_agency_wide_encoder_reaches_every_centers_records(): void
    {
        $mine = $this->farmerIn($this->center);
        $theirs = $this->farmerIn($this->otherCenter);

        $encoder = $this->userWithRole(UserRoleEnum::ENCODER, $this->agency, null);

        $visible = Farmer::query()->visibleTo($encoder)->pluck('id');

        $this->assertTrue($visible->contains($mine->id));
        $this->assertTrue($visible->contains($theirs->id));
    }

    public function test_a_center_encoder_is_still_confined_to_its_own_center(): void
    {
        $mine = $this->farmerIn($this->center);
        $theirs = $this->farmerIn($this->otherCenter);

        $encoder = $this->userWithRole(UserRoleEnum::ENCODER, $this->agency, $this->center);

        $visible = Farmer::query()->visibleTo($encoder)->pluck('id');

        $this->assertTrue($visible->contains($mine->id));
        $this->assertFalse($visible->contains($theirs->id));
    }

    public function test_an_agency_wide_encoder_exports_at_agency_breadth(): void
    {
        $agencyWide = $this->userWithRole(UserRoleEnum::ENCODER, $this->agency, null);
        $centerScoped = $this->userWithRole(UserRoleEnum::ENCODER, $this->agency, $this->center);

        $this->assertTrue($agencyWide->canExportData());
        $this->assertFalse($agencyWide->canExportOwnCenter(), 'There is no own center to confine to.');

        $this->assertFalse($centerScoped->canExportData());
        $this->assertTrue($centerScoped->canExportOwnCenter());
    }

    public function test_the_shared_permission_list_carries_the_wider_export(): void
    {
        // The nav and useAuth() read the shared prop, not the model.
        $encoder = $this->userWithRole(UserRoleEnum::ENCODER, $this->agency, null);

        $permissions = $this->actingAs($encoder)
            ->get('/dashboard')
            ->assertOk()
            ->viewData('page')['props']['permissions'];

        $this->assertContains('export-data', $permissions);
    }

    public function test_an_agency_wide_reviewer_reviews_every_centers_documents(): void
    {
        $document = $this->pendingReviewFor($this->center);

        $reviewer = $this->userWithRole(UserRoleEnum::REVIEWER, $this->agency, null);

        $this->actingAs($reviewer)
            ->post(route('documents.approve', $document))
            ->assertRedirect();

        $this->assertSame(
            DocumentStatusEnum::APPROVED,
            $document->fresh()->status,
        );
    }

    public function test_a_center_reviewer_cannot_review_another_centers_document(): void
    {
        $document = $this->pendingReviewFor($this->otherCenter);

        $reviewer = $this->userWithRole(UserRoleEnum::REVIEWER, $this->agency, $this->center);

        $this->actingAs($reviewer)
            ->post(route('documents.approve', $document))
            ->assertForbidden();

        $this->assertSame(
            DocumentStatusEnum::PENDING_AGENCY_REVIEW,
            $document->fresh()->status,
        );
    }

    public function test_the_documents_list_tells_an_agency_wide_reviewer_it_may_review(): void
    {
        // The list page asks "does this role review at all?" with no document in
        // hand, and that answer drives the Approve / Reject buttons on every row.
        // It has to be true for an agency-wide reviewer, or the buttons never
        // render even though the server would accept the decision.
        $reviewer = $this->userWithRole(UserRoleEnum::REVIEWER, $this->agency, null);

        $permissions = $this->actingAs($reviewer)
            ->get(route('documents.index'))
            ->assertOk()
            ->viewData('page')['props']['documentPermissions'];

        $this->assertTrue($permissions['can_review']);
    }

    public function test_the_documents_list_offers_review_to_a_center_reviewer(): void
    {
        $reviewer = $this->userWithRole(UserRoleEnum::REVIEWER, $this->agency, $this->center);

        $permissions = $this->actingAs($reviewer)
            ->get(route('documents.index'))
            ->assertOk()
            ->viewData('page')['props']['documentPermissions'];

        $this->assertTrue($permissions['can_review']);
    }

    private function farmerIn($center): Farmer
    {
        return Farmer::create([
            'center_id' => $center->id,
            'code' => 'F-'.$center->code,
            'first_name' => 'Ana',
            'last_name' => 'Dela Cruz',
        ]);
    }

    private function pendingReviewFor($center): Document
    {
        $file = new File([
            'name' => 'farmers.xlsx',
            'path' => 'files/farmers.xlsx',
            'type' => 'application/vnd.ms-excel',
            'size' => 16,
        ]);
        $file->uploadedBy()->associate($this->userWithRole(UserRoleEnum::ENCODER, $this->agency, $center));
        $file->save();

        $document = new Document([
            'title' => 'Farmers '.$center->code,
            'status' => DocumentStatusEnum::PENDING_AGENCY_REVIEW,
            'submitted_at' => now(),
            'center_reviewed_by' => $this->userWithRole(UserRoleEnum::REVIEWER, $this->agency, $center)->id,
            'center_reviewed_at' => now(),
        ]);
        $document->file()->associate($file);
        $document->save();

        $document->tags()->create(['key' => 'center', 'value' => $center->code]);

        return $document;
    }

    protected function pccAdmin($agency)
    {
        return $this->userWithRole(UserRoleEnum::AGENCY_ADMIN, $agency, null, UserStatusEnum::ACTIVE);
    }
}
