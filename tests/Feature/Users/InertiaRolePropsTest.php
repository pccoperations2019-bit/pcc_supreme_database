<?php

namespace Tests\Feature\Users;

use App\Enums\UserRoleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Inertia props the frontend role checks depend on.
 *
 * useAuth() reads auth.user.roles and MainNav.vue reads props.permissions. Both
 * were previously absent, so every client-side hasRole() check resolved false
 * and every permission-gated nav link was hidden.
 */
class InertiaRolePropsTest extends TestCase
{
    use InteractsWithUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_the_authenticated_user_carries_their_roles(): void
    {
        $admin = $this->pccAdmin($this->agency());

        $props = $this->actingAs($admin)->get('/users')->viewData('page')['props'];

        $roleNames = collect($props['auth']['user']['roles'])->pluck('name');

        $this->assertTrue($roleNames->contains(UserRoleEnum::AGENCY_ADMIN->value));
    }

    public function test_permissions_are_shared_at_the_top_level_for_the_nav(): void
    {
        $admin = $this->pccAdmin($this->agency());

        $props = $this->actingAs($admin)->get('/users')->viewData('page')['props'];

        // MainNav.vue reads page.props.permissions, not props.auth.permissions.
        $this->assertArrayHasKey('permissions', $props);
        $this->assertContains('view-users', $props['permissions']);
        $this->assertContains('approve-users', $props['permissions']);
    }

    public function test_a_read_only_role_sees_a_shorter_permission_list(): void
    {
        $executive = $this->userWithRole(UserRoleEnum::EXECUTIVE, $this->agency());

        // The Executive cannot open the user list at all, which is the point:
        // the permission list, not the role name, is what closes the door.
        $this->actingAs($executive)->get('/users')->assertForbidden();

        // On a page they can open, the list carries 'export-data' — the
        // Generate Report button is visible, so the permission has to be there
        // or clicking it 403s — but nothing that writes.
        $props = $this->actingAs($executive)
            ->get('/dashboard')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertContains('view-reports', $props['permissions']);
        $this->assertContains('export-data', $props['permissions']);
        $this->assertNotContains('enter-data', $props['permissions']);
        $this->assertNotContains('create-users', $props['permissions']);
    }

    public function test_permissions_are_an_empty_list_for_a_guest(): void
    {
        $props = $this->get('/login')->viewData('page')['props'];

        $this->assertSame([], $props['permissions']);
    }

    public function test_view_audits_reaches_the_reviewers_and_not_the_encoders(): void
    {
        // MainNav.vue hides the Audit Trail link without 'view-audits', and the
        // route gates on the same permission - so this one assertion covers both
        // halves. The encoder is the negative case: it enters data, never reviews
        // it, so it must not be offered the log.
        $agency = $this->agency();
        $center = $this->center($agency);

        $reviewer = $this->userWithRole(UserRoleEnum::REVIEWER, $agency, $center);
        $encoder = $this->userWithRole(UserRoleEnum::ENCODER, $agency, $center);

        $reviewerProps = $this->actingAs($reviewer)
            ->get('/dashboard')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertContains('view-audits', $reviewerProps['permissions']);

        $encoderProps = $this->actingAs($encoder)
            ->get('/dashboard')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertNotContains('view-audits', $encoderProps['permissions']);

        $this->actingAs($encoder)->get(route('audits.index'))->assertForbidden();
    }
}
