<?php

namespace Tests\Feature\Users;

use App\Enums\UserRoleEnum;
use App\Models\User;
use App\Services\UserProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The role/scope rules the permission matrix cannot express: which center an
 * account may be attached to, and who is allowed to grant which role.
 */
class StoreUserScopeTest extends TestCase
{
    use InteractsWithUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_a_center_admin_requires_a_center(): void
    {
        // Encoder and Reviewer may be provisioned agency-wide - see
        // AgencyWideEncoderReviewerTest - but the Center Admin is defined by the
        // center it administers, so it cannot be created without one.
        $agency = $this->agency();
        $admin = $this->pccAdmin($agency);

        $this->actingAs($admin)
            ->post('/users', $this->userPayload([
                'role' => UserRoleEnum::CENTER_ADMIN->value,
                'center_id' => null,
            ]))
            ->assertSessionHasErrors('center_id');
    }

    public function test_a_pcc_level_role_cannot_carry_a_center(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);
        $admin = $this->pccAdmin($agency);

        $this->actingAs($admin)
            ->post('/users', $this->userPayload([
                'role' => UserRoleEnum::EXECUTIVE->value,
                'center_id' => $center->id,
            ]))
            ->assertSessionHasErrors('center_id');
    }

    public function test_a_center_scoped_creator_is_pinned_to_their_own_center(): void
    {
        $agency = $this->agency();
        $mine = $this->center($agency, 'MINE');
        $theirs = $this->center($agency, 'THEIRS');

        $encoder = $this->userWithRole(UserRoleEnum::CENTER_ADMIN, $agency, $mine);

        // The client asks for the other center; the request must ignore it.
        $this->actingAs($encoder)
            ->post('/users', $this->userPayload([
                'role' => UserRoleEnum::REVIEWER->value,
                'center_id' => $theirs->id,
                'email' => 'sneaky@pcc.gov.ph',
                'username' => 'sneaky',
            ]))
            ->assertSessionHasNoErrors();

        $created = User::where('email', 'sneaky@pcc.gov.ph')->firstOrFail();

        $this->assertSame($mine->id, $created->center_id);
    }

    public function test_a_center_scoped_creator_may_not_grant_a_pcc_level_role(): void
    {
        $agency = $this->agency();
        $mine = $this->center($agency, 'MINE');
        $encoder = $this->userWithRole(UserRoleEnum::CENTER_ADMIN, $agency, $mine);

        $this->actingAs($encoder)
            ->post('/users', $this->userPayload([
                'role' => UserRoleEnum::EXECUTIVE->value,
                'center_id' => null,
                'email' => 'pope@pcc.gov.ph',
                'username' => 'pope',
            ]))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'pope@pcc.gov.ph']);
    }

    public function test_a_center_in_another_agency_is_rejected(): void
    {
        $agency = $this->agency('PCC');
        $otherAgency = $this->agency('OTHER');
        $otherCenter = $this->center($otherAgency, 'OTHERC');

        $admin = $this->pccAdmin($agency);

        $this->actingAs($admin)
            ->post('/users', $this->userPayload([
                'role' => UserRoleEnum::REVIEWER->value,
                'center_id' => $otherCenter->id,
                'email' => 'cross@pcc.gov.ph',
                'username' => 'cross_agency',
            ]))
            ->assertSessionHasErrors('center_id');
    }

    public function test_the_provisioning_service_refuses_a_mismatched_scope(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);

        $service = app(UserProvisioningService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->create(
            name: 'Bad Scope',
            email: 'bad.scope@pcc.gov.ph',
            username: 'bad_scope',
            password: 'RcXA3HcPwo0W',
            role: UserRoleEnum::EXECUTIVE,
            center: $center,
        );
    }
}
