<?php

namespace Tests\Feature\Users;

use App\Enums\UserRoleEnum;
use App\Enums\UserStatusEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * New accounts are pending and cannot sign in until an approver activates them,
 * and nobody may approve an account they created themselves.
 */
class UserApprovalTest extends TestCase
{
    use InteractsWithUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    /**
     * Provision a pending account and return it.
     */
    private function pendingUser(User $creator, ?object $center = null): User
    {
        $this->actingAs($creator)->post('/users', $this->userPayload([
            'center_id' => $center?->id,
            'email' => 'pending@pcc.gov.ph',
            'username' => 'pending_user',
        ]));

        return User::where('email', 'pending@pcc.gov.ph')->firstOrFail();
    }

    public function test_a_pending_account_cannot_authenticate(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);
        $admin = $this->pccAdmin($agency);

        $user = $this->pendingUser($admin, $center);

        $this->assertSame(UserStatusEnum::PENDING, $user->status);

        $this->post('/logout');
        $this->assertGuest();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'RcXA3HcPwo0W',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_approved_account_can_authenticate(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);
        $admin = $this->pccAdmin($agency);
        $approver = $this->pccAdmin($agency);

        $user = $this->pendingUser($admin, $center);

        $this->actingAs($approver)->patch("/users/{$user->id}/approve")->assertRedirect();

        $user->refresh();

        $this->assertSame(UserStatusEnum::ACTIVE, $user->status);
        $this->assertSame($approver->id, $user->approved_by);
        $this->assertNotNull($user->approved_at);

        $this->post('/logout');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'RcXA3HcPwo0W',
        ])->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_rejected_account_cannot_authenticate_and_may_be_resubmitted(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);
        $admin = $this->pccAdmin($agency);
        $approver = $this->pccAdmin($agency);

        $user = $this->pendingUser($admin, $center);

        $this->actingAs($approver)->patch("/users/{$user->id}/reject")->assertRedirect();

        $user->refresh();
        $this->assertSame(UserStatusEnum::REJECTED, $user->status);

        $this->post('/logout');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'RcXA3HcPwo0W',
        ])->assertSessionHasErrors('email');

        // A rejected account is still awaiting a decision, so it can be approved.
        $this->actingAs($approver)->patch("/users/{$user->id}/approve")->assertRedirect();
        $this->assertSame(UserStatusEnum::ACTIVE, $user->fresh()->status);
    }

    public function test_the_sole_approver_may_approve_the_account_they_created(): void
    {
        // Only agency-admin holds 'approve-users'. With a single such account the
        // strict rule deadlocks — it could never activate anything, including a
        // second approver — so it yields when nobody else can decide.
        $agency = $this->agency();
        $center = $this->center($agency);
        $admin = $this->pccAdmin($agency);

        $user = $this->pendingUser($admin, $center);

        $this->actingAs($admin)
            ->patch("/users/{$user->id}/approve")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(UserStatusEnum::ACTIVE, $user->fresh()->status);
        $this->assertSame($admin->id, $user->fresh()->approved_by);
    }

    public function test_the_creator_is_blocked_once_another_approver_exists(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);
        $admin = $this->pccAdmin($agency);

        // A second active approver makes separation of duties possible, so the
        // rule applies again.
        $this->pccAdmin($agency);

        $user = $this->pendingUser($admin, $center);

        $this->actingAs($admin)
            ->patch("/users/{$user->id}/approve")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(UserStatusEnum::PENDING, $user->fresh()->status);
    }

    public function test_an_approver_never_approves_their_own_account(): void
    {
        // Unchanged regardless of how many approvers exist.
        $agency = $this->agency();
        $admin = $this->pccAdmin($agency);

        $this->actingAs($admin)
            ->patch("/users/{$admin->id}/approve")
            ->assertForbidden();
    }

    public function test_an_account_without_approve_users_cannot_approve(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);
        $admin = $this->pccAdmin($agency);
        $this->pccAdmin($agency);

        $user = $this->pendingUser($admin, $center);

        // The Executive is read-only: no 'approve-users'.
        $executive = $this->userWithRole(UserRoleEnum::EXECUTIVE, $agency);

        $this->actingAs($executive)
            ->patch("/users/{$user->id}/approve")
            ->assertForbidden();

        $this->assertSame(UserStatusEnum::PENDING, $user->fresh()->status);
    }

    public function test_an_already_active_account_cannot_be_approved_again(): void
    {
        $agency = $this->agency();
        $this->pccAdmin($agency);

        $active = $this->userWithRole(UserRoleEnum::EXECUTIVE, $agency);
        $approver = $this->pccAdmin($agency);

        $this->actingAs($approver)
            ->patch("/users/{$active->id}/approve")
            ->assertForbidden();
    }

    public function test_approval_is_audited(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);
        $admin = $this->pccAdmin($agency);
        $approver = $this->pccAdmin($agency);

        $user = $this->pendingUser($admin, $center);

        $this->actingAs($approver)->patch("/users/{$user->id}/approve");

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => $user->getMorphClass(),
            'subject_id' => $user->id,
            'event' => 'updated',
        ]);
    }

    public function test_a_deactivated_account_cannot_authenticate(): void
    {
        $agency = $this->agency();
        $user = $this->userWithRole(UserRoleEnum::EXECUTIVE, $agency);

        $user->forceFill(['status' => UserStatusEnum::DEACTIVATED])->save();

        $this->post('/logout');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertTrue(Hash::check('password', $user->password));
    }
}
