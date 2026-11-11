<?php

namespace Tests\Feature\Users;

use App\Enums\UserRoleEnum;
use App\Enums\UserStatusEnum;
use App\Models\Center;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A center-scoped role must never see another center's data. This is the class
 * of leak the old hasRole() checks invited: 76 of the 80 backend checks only
 * knew about the encoder, so the reviewer inherited agency-wide visibility.
 */
class UserRoleScopingTest extends TestCase
{
    use InteractsWithUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_a_center_scoped_role_only_sees_their_own_centers_on_the_user_list(): void
    {
        $agency = $this->agency();
        $mine = $this->center($agency, 'MINE');
        $theirs = $this->center($agency, 'THEIRS');

        $admin = $this->pccAdmin($agency);
        $this->actingAs($admin)->post('/users', $this->userPayload([
            'role' => UserRoleEnum::CENTER_ADMIN->value,
            'center_id' => $mine->id,
            'email' => 'mine.encoder@pcc.gov.ph',
            'username' => 'mine_encoder',
        ]));
        $this->actingAs($admin)->post('/users', $this->userPayload([
            'role' => UserRoleEnum::CENTER_ADMIN->value,
            'center_id' => $theirs->id,
            'email' => 'theirs.encoder@pcc.gov.ph',
            'username' => 'theirs_encoder',
        ]));

        $encoder = User::where('email', 'mine.encoder@pcc.gov.ph')->firstOrFail();
        $encoder->forceFill(['status' => UserStatusEnum::ACTIVE])->save();

        $this->actingAs($encoder)
            ->get('/users')
            ->assertOk()
            ->assertSee('mine.encoder@pcc.gov.ph')
            ->assertDontSee('theirs.encoder@pcc.gov.ph');
    }

    public function test_a_reviewer_cannot_open_another_centers_user(): void
    {
        $agency = $this->agency();
        $mine = $this->center($agency, 'MINE');
        $theirs = $this->center($agency, 'THEIRS');
        $admin = $this->pccAdmin($agency);

        $this->actingAs($admin)->post('/users', $this->userPayload([
            'role' => UserRoleEnum::REVIEWER->value,
            'center_id' => $theirs->id,
            'email' => 'theirs.reviewer@pcc.gov.ph',
            'username' => 'theirs_reviewer',
        ]));

        $otherReviewer = User::where('email', 'theirs.reviewer@pcc.gov.ph')->firstOrFail();
        $otherReviewer->forceFill(['status' => UserStatusEnum::ACTIVE])->save();

        $mineReviewer = $this->userWithRole(UserRoleEnum::REVIEWER, $agency, $mine);

        $this->actingAs($mineReviewer)
            ->get("/users/{$otherReviewer->id}")
            ->assertForbidden();
    }

    public function test_a_pcc_administrator_only_sees_its_own_agency(): void
    {
        $agency = $this->agency('PCC');
        $otherAgency = $this->agency('OTHER');

        $admin = $this->pccAdmin($agency);

        $stranger = $this->userWithRole(UserRoleEnum::EXECUTIVE, $otherAgency);
        $colleague = $this->userWithRole(UserRoleEnum::EXECUTIVE, $agency);

        $this->actingAs($admin)
            ->get('/users')
            ->assertOk()
            ->assertSee($colleague->email)
            ->assertDontSee($stranger->email);
    }

    public function test_a_center_scoped_role_with_no_center_sees_nothing(): void
    {
        // Deny by default: a misconfigured account must not fall through to
        // agency-wide visibility.
        $agency = $this->agency();
        $center = $this->center($agency);
        $this->pccAdmin($agency);

        $orphan = User::factory()->create(['username' => 'orphan_encoder']);
        $orphan->assignRole(UserRoleEnum::CENTER_ADMIN->value);
        $orphan->forceFill(['status' => UserStatusEnum::ACTIVE])->save();

        $colleague = $this->userWithRole(UserRoleEnum::CENTER_ADMIN, $agency, $center);

        $this->actingAs($colleague)
            ->get('/users')
            ->assertOk()
            ->assertDontSee($orphan->email);
    }

    public function test_scoped_to_center_hides_other_centers_records(): void
    {
        $agency = $this->agency();
        $mine = $this->center($agency, 'MINE');
        $theirs = $this->center($agency, 'THEIRS');

        $reviewer = $this->userWithRole(UserRoleEnum::REVIEWER, $agency, $mine);

        $visible = Center::visibleTo($reviewer)->pluck('id')->all();

        $this->assertSame([$mine->id], $visible);
        $this->assertNotContains($theirs->id, $visible);
    }

    public function test_a_reviewer_cannot_reach_the_user_list_at_all(): void
    {
        // The reviewer holds no 'view-users', so the whole module is closed to
        // them rather than partially filtered.
        $agency = $this->agency();
        $center = $this->center($agency);

        $reviewer = $this->userWithRole(UserRoleEnum::REVIEWER, $agency, $center);

        $this->actingAs($reviewer)->get('/users')->assertForbidden();
    }

    public function test_every_role_has_a_human_readable_label(): void
    {
        // Five distinct roles. Center Admin manages the center and its accounts;
        // Encoder is a separate, lesser data-entry role.
        $this->assertSame([
            'agency-admin' => 'PCC Administrator',
            'executive' => 'Executive',
            'center-admin' => 'Center Admin',
            'encoder' => 'Encoder',
            'reviewer' => 'Reviewer',
        ], UserRoleEnum::labels());

        $this->assertCount(5, UserRoleEnum::cases());

        foreach (UserRoleEnum::cases() as $case) {
            $this->assertNotSame($case->value, $case->label());
        }
    }

    public function test_the_role_enum_reports_the_scope_of_each_role(): void
    {
        $this->assertTrue(UserRoleEnum::CENTER_ADMIN->isCenterScoped());
        $this->assertTrue(UserRoleEnum::ENCODER->isCenterScoped());
        $this->assertTrue(UserRoleEnum::REVIEWER->isCenterScoped());
        $this->assertFalse(UserRoleEnum::AGENCY_ADMIN->isCenterScoped());
        $this->assertFalse(UserRoleEnum::EXECUTIVE->isCenterScoped());

        $this->assertTrue(UserRoleEnum::AGENCY_ADMIN->isPccLevel());
        $this->assertTrue(UserRoleEnum::EXECUTIVE->isPccLevel());
        $this->assertFalse(UserRoleEnum::REVIEWER->isPccLevel());
    }
}
