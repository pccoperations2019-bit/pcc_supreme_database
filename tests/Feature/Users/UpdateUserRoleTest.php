<?php

namespace Tests\Feature\Users;

use App\Enums\UserRoleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UpdateUserRoleTest extends TestCase
{
    use InteractsWithUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_a_role_can_be_changed_by_a_pcc_administrator(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);
        $admin = $this->pccAdmin($agency);
        $encoder = $this->userWithRole(UserRoleEnum::CENTER_ADMIN, $agency, $center);

        $this->actingAs($admin)->patch("/users/{$encoder->id}", [
            'name' => $encoder->name,
            'email' => $encoder->email,
            'username' => $encoder->username,
            'role' => UserRoleEnum::REVIEWER->value,
            'center_id' => $center->id,
        ])->assertSessionHasNoErrors();

        $encoder->refresh();

        $this->assertTrue($encoder->hasRole(UserRoleEnum::REVIEWER->value));
        $this->assertFalse($encoder->hasRole(UserRoleEnum::CENTER_ADMIN->value));
    }

    public function test_changing_a_role_needs_the_assign_roles_permission(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);

        // Both accounts are encoders: neither holds 'assign-roles'.
        $actor = $this->userWithRole(UserRoleEnum::CENTER_ADMIN, $agency, $center);
        $target = $this->userWithRole(UserRoleEnum::CENTER_ADMIN, $agency, $center);

        $this->actingAs($actor)->patch("/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'username' => $target->username,
            'role' => UserRoleEnum::REVIEWER->value,
            'center_id' => $center->id,
        ])->assertSessionHasErrors('role');

        $this->assertTrue($target->fresh()->hasRole(UserRoleEnum::CENTER_ADMIN->value));
    }

    public function test_editing_a_profile_without_touching_the_role_is_allowed(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);
        $admin = $this->pccAdmin($agency);
        $target = $this->userWithRole(UserRoleEnum::CENTER_ADMIN, $agency, $center);

        $this->actingAs($admin)->patch("/users/{$target->id}", [
            'name' => 'Renamed Person',
            'email' => $target->email,
            'username' => $target->username,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Renamed Person', $target->fresh()->name);
    }

    public function test_a_blank_password_leaves_the_credentials_alone(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);
        $admin = $this->pccAdmin($agency);
        $encoder = $this->userWithRole(UserRoleEnum::CENTER_ADMIN, $agency, $center);

        $originalHash = $encoder->password;

        $this->actingAs($admin)->patch("/users/{$encoder->id}", [
            'name' => 'Renamed Encoder',
            'email' => $encoder->email,
            'username' => $encoder->username,
            'password' => '',
        ])->assertSessionHasNoErrors();

        $encoder->refresh();

        $this->assertSame($originalHash, $encoder->password);
        $this->assertTrue(Hash::check('password', $encoder->password));
    }

    public function test_a_supplied_password_is_rehashed(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);
        $admin = $this->pccAdmin($agency);
        $encoder = $this->userWithRole(UserRoleEnum::CENTER_ADMIN, $agency, $center);

        $this->actingAs($admin)->patch("/users/{$encoder->id}", [
            'name' => $encoder->name,
            'email' => $encoder->email,
            'username' => $encoder->username,
            'password' => 'N3wPassphrase!',
            'password_confirmation' => 'N3wPassphrase!',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('N3wPassphrase!', $encoder->fresh()->password));
    }

    public function test_a_user_cannot_edit_their_own_account_through_this_screen(): void
    {
        $agency = $this->agency();
        $this->center($agency);
        $admin = $this->pccAdmin($agency);

        $this->actingAs($admin)->patch("/users/{$admin->id}", [
            'name' => 'Someone Else',
            'email' => $admin->email,
            'username' => $admin->username,
        ])->assertForbidden();
    }

    public function test_a_pcc_level_role_releases_the_center_on_update(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);
        $admin = $this->pccAdmin($agency);

        $encoder = $this->userWithRole(UserRoleEnum::CENTER_ADMIN, $agency, $center);

        $this->actingAs($admin)->patch("/users/{$encoder->id}", [
            'name' => $encoder->name,
            'email' => $encoder->email,
            'username' => $encoder->username,
            'role' => UserRoleEnum::EXECUTIVE->value,
            'center_id' => null,
        ])->assertSessionHasNoErrors();

        $encoder->refresh();

        $this->assertTrue($encoder->hasRole(UserRoleEnum::EXECUTIVE->value));
        $this->assertNull($encoder->center_id);
        $this->assertSame($agency->id, $encoder->agency_id);
    }
}
