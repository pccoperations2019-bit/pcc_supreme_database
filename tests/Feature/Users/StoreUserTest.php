<?php

namespace Tests\Feature\Users;

use App\Enums\UserRoleEnum;
use App\Enums\UserStatusEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StoreUserTest extends TestCase
{
    use InteractsWithUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_a_pcc_admin_creates_a_center_scoped_account_that_awaits_approval(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);
        $admin = $this->pccAdmin($agency);

        $this->actingAs($admin)
            ->post('/users', $this->userPayload(['center_id' => $center->id]))
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'encoder.one@pcc.gov.ph')->firstOrFail();

        $this->assertSame(UserStatusEnum::PENDING, $user->status);
        $this->assertTrue($user->hasRole(UserRoleEnum::CENTER_ADMIN->value));
        $this->assertSame($center->id, $user->center_id);
        $this->assertSame($agency->id, $user->agency_id);
        $this->assertNull($user->approved_by);
        // Assigned plaintext must reach the database hashed.
        $this->assertTrue(Hash::check('RcXA3HcPwo0W', $user->password));
    }

    public function test_a_pcc_level_account_is_created_without_a_center(): void
    {
        $agency = $this->agency();
        $admin = $this->pccAdmin($agency);

        $this->actingAs($admin)
            ->post('/users', $this->userPayload([
                'role' => UserRoleEnum::EXECUTIVE->value,
                'center_id' => null,
                'username' => 'exec_one',
                'email' => 'exec.one@pcc.gov.ph',
            ]))
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'exec.one@pcc.gov.ph')->firstOrFail();

        $this->assertTrue($user->hasRole(UserRoleEnum::EXECUTIVE->value));
        $this->assertNull($user->center_id);
        $this->assertSame($agency->id, $user->agency_id);
    }

    public function test_duplicate_email_and_username_are_rejected(): void
    {
        $agency = $this->agency();
        $admin = $this->pccAdmin($agency);
        $center = $this->center($agency);

        $this->actingAs($admin)->post('/users', $this->userPayload([
            'center_id' => $center->id,
        ]));

        $this->actingAs($admin)
            ->post('/users', $this->userPayload([
                'center_id' => $center->id,
                'name' => 'Encoder Two',
            ]))
            ->assertSessionHasErrors(['email', 'username']);
    }

    public function test_creating_an_account_is_audited(): void
    {
        $agency = $this->agency();
        $admin = $this->pccAdmin($agency);
        $center = $this->center($agency);

        $this->actingAs($admin)->post('/users', $this->userPayload([
            'center_id' => $center->id,
        ]));

        $user = User::where('email', 'encoder.one@pcc.gov.ph')->firstOrFail();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => $user->getMorphClass(),
            'subject_id' => $user->id,
            'event' => 'created',
        ]);
    }

    public function test_an_actor_without_create_users_cannot_create_an_account(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);
        // The Executive is read-only and holds no user-management permission.
        $executive = $this->userWithRole(UserRoleEnum::EXECUTIVE, $agency);

        $this->actingAs($executive)
            ->post('/users', $this->userPayload(['center_id' => $center->id]))
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'encoder.one@pcc.gov.ph']);
    }
}
