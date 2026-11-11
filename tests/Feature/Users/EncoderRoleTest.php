<?php

namespace Tests\Feature\Users;

use App\Enums\UserRoleEnum;
use App\Enums\UserStatusEnum;
use App\Models\Center;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Center Admin and Encoder are two distinct center-level roles, not one account
 * under two names. The Encoder is the lesser of the two: it enters data and
 * submits documents, and holds no user-management or review authority.
 */
class EncoderRoleTest extends TestCase
{
    use InteractsWithUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_the_encoder_is_scoped_to_a_center(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);

        $encoder = $this->userWithRole(UserRoleEnum::ENCODER, $agency, $center);

        $this->assertTrue($encoder->isCenterScoped());
        $this->assertFalse($encoder->isPccLevel());
        $this->assertSame($center->id, $encoder->center_id);
    }

    public function test_an_encoder_may_be_provisioned_for_the_whole_agency(): void
    {
        // The centre-scoped encoder above is the ordinary case. An encoder with no
        // center is the agency's data-entry officer: still no user-management and
        // no review authority, but no longer confined to one center.
        $agency = $this->agency();
        $admin = $this->pccAdmin($agency);

        $this->actingAs($admin)
            ->post('/users', $this->userPayload([
                'role' => UserRoleEnum::ENCODER->value,
                'center_id' => null,
                'username' => 'agency_encoder',
                'email' => 'agency.encoder@pcc.gov.ph',
            ]))
            ->assertSessionHasNoErrors();

        $encoder = User::where('email', 'agency.encoder@pcc.gov.ph')->firstOrFail();

        $this->assertNull($encoder->center_id);
        $this->assertFalse($encoder->isCenterScoped());
        $this->assertTrue($encoder->isPccLevel());
        $this->assertFalse($encoder->can('review-documents'), 'Nationwide reach is not review authority.');
    }

    public function test_a_center_admin_still_needs_a_center(): void
    {
        $agency = $this->agency();
        $admin = $this->pccAdmin($agency);

        $this->actingAs($admin)
            ->post('/users', $this->userPayload([
                'role' => UserRoleEnum::CENTER_ADMIN->value,
                'center_id' => null,
                'username' => 'homeless_center_admin',
                'email' => 'homeless.center.admin@pcc.gov.ph',
            ]))
            ->assertSessionHasErrors('center_id');
    }

    public function test_an_encoder_may_enter_data_and_submit_documents(): void
    {
        $agency = $this->agency();
        $encoder = $this->userWithRole(UserRoleEnum::ENCODER, $agency, $this->center($agency));

        $this->assertTrue($encoder->can('enter-data'));
        $this->assertTrue($encoder->can('submit-documents'));
    }

    public function test_an_encoder_holds_no_user_management_authority(): void
    {
        $agency = $this->agency();
        $encoder = $this->userWithRole(UserRoleEnum::ENCODER, $agency, $this->center($agency));

        foreach (['view-users', 'create-users', 'update-users', 'deactivate-users', 'assign-roles', 'approve-users'] as $permission) {
            $this->assertFalse($encoder->can($permission), "encoder must not hold {$permission}");
        }
    }

    public function test_an_encoder_cannot_review_documents(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);
        $admin = $this->pccAdmin($agency);

        $this->actingAs($admin)->post('/users', $this->userPayload([
            'role' => UserRoleEnum::REVIEWER->value,
            'center_id' => $center->id,
            'username' => 'the_reviewer',
            'email' => 'the.reviewer@pcc.gov.ph',
        ]));

        $reviewer = User::where('email', 'the.reviewer@pcc.gov.ph')->firstOrFail();
        $reviewer->forceFill(['status' => UserStatusEnum::ACTIVE])->save();

        $encoder = $this->userWithRole(UserRoleEnum::ENCODER, $agency, $center);

        // An encoder must be refused the user list entirely, and must not be able
        // to provision the reviewer that reviews its own submissions.
        $this->actingAs($encoder)->get('/users')->assertForbidden();

        $this->actingAs($encoder)->post('/users', $this->userPayload([
            'role' => UserRoleEnum::REVIEWER->value,
            'center_id' => $center->id,
            'username' => 'self_review',
            'email' => 'self.review@pcc.gov.ph',
        ]))->assertForbidden();
    }

    public function test_a_center_admin_outranks_an_encoder(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);

        $admin = $this->userWithRole(UserRoleEnum::CENTER_ADMIN, $agency, $center);
        $encoder = $this->userWithRole(UserRoleEnum::ENCODER, $agency, $center);

        // Managing the center and its accounts is the Center Admin's alone.
        $this->assertTrue($admin->can('manage-center'));
        $this->assertTrue($admin->can('manage-farmers'));
        $this->assertTrue($admin->can('create-users'));

        $this->assertFalse($encoder->can('manage-center'));
        $this->assertFalse($encoder->can('manage-farmers'));
        $this->assertFalse($encoder->can('create-users'));
    }

    public function test_a_center_admin_may_provision_an_encoder(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);
        $admin = $this->userWithRole(UserRoleEnum::CENTER_ADMIN, $agency, $center);

        $this->actingAs($admin)
            ->post('/users', $this->userPayload([
                'role' => UserRoleEnum::ENCODER->value,
                'center_id' => $center->id,
                'username' => 'new_encoder',
                'email' => 'new.encoder@pcc.gov.ph',
            ]))
            ->assertSessionHasNoErrors();

        $encoder = User::where('email', 'new.encoder@pcc.gov.ph')->firstOrFail();

        $this->assertTrue($encoder->hasRole(UserRoleEnum::ENCODER->value));
        $this->assertSame($center->id, $encoder->center_id);
    }

    public function test_a_center_admin_may_not_provision_itself_a_pcc_role(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);
        $admin = $this->userWithRole(UserRoleEnum::CENTER_ADMIN, $agency, $center);

        $this->actingAs($admin)
            ->post('/users', $this->userPayload([
                'role' => UserRoleEnum::AGENCY_ADMIN->value,
                'center_id' => null,
                'username' => 'wants_promotion',
                'email' => 'wants.promotion@pcc.gov.ph',
            ]))
            ->assertSessionHasErrors('role');
    }

    public function test_an_encoder_only_sees_its_own_center(): void
    {
        $agency = $this->agency();
        $mine = $this->center($agency, 'MINE');
        $theirs = $this->center($agency, 'THEIRS');

        $encoder = $this->userWithRole(UserRoleEnum::ENCODER, $agency, $mine);

        $visible = Center::visibleTo($encoder)->pluck('id')->all();

        $this->assertSame([$mine->id], $visible);
        $this->assertNotContains($theirs->id, $visible);
    }
}
