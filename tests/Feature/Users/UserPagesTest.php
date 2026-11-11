<?php

namespace Tests\Feature\Users;

use App\Enums\UserRoleEnum;
use App\Enums\UserStatusEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The props the Users Vue pages consume.
 *
 * These pages are resolved by Inertia from a glob at runtime, so a PHP test
 * cannot catch a broken template — but it can pin the controller contract the
 * templates bind to, which is where a missing or renamed prop would otherwise
 * surface only as a blank screen.
 */
class UserPagesTest extends TestCase
{
    use InteractsWithUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_the_index_page_shares_users_filters_and_permissions(): void
    {
        $agency = $this->agency();
        $admin = $this->pccAdmin($agency);

        $this->actingAs($admin)->post('/users', $this->userPayload([
            'role' => UserRoleEnum::EXECUTIVE->value,
            'center_id' => null,
            'username' => 'listed_exec',
            'email' => 'listed.exec@pcc.gov.ph',
        ]));

        $props = $this->actingAs($admin)
            ->get('/users')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertArrayHasKey('users', $props);
        $this->assertArrayHasKey('filters', $props);
        $this->assertArrayHasKey('status', $props['filters']);
        $this->assertArrayHasKey('role', $props['filters']);

        // The row shape UserTable.vue reads.
        $row = $props['users']['data'][0];
        foreach (['id', 'name', 'email', 'username', 'status', 'roles', 'center', 'created_at'] as $key) {
            $this->assertArrayHasKey($key, $row);
        }
    }

    public function test_the_index_defaults_to_newest_users_first(): void
    {
        $agency = $this->agency();
        $admin = $this->pccAdmin($agency);
        $older = $this->userWithRole(UserRoleEnum::EXECUTIVE, $agency);
        $newer = $this->userWithRole(UserRoleEnum::EXECUTIVE, $agency);

        $older->forceFill(['name' => 'A Older', 'created_at' => now()->subDays(2)])->saveQuietly();
        $newer->forceFill(['name' => 'Z Newer', 'created_at' => now()->subDay()])->saveQuietly();

        $rows = $this->actingAs($admin)
            ->get('/users')
            ->assertOk()
            ->viewData('page')['props']['users']['data'];

        $ids = collect($rows)
            ->pluck('id')
            ->filter(fn ($id) => in_array($id, [$older->id, $newer->id], true))
            ->values()
            ->all();

        $this->assertSame([$newer->id, $older->id], $ids);
    }

    public function test_the_create_page_shares_centers_and_enums(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);
        $admin = $this->pccAdmin($agency);

        $props = $this->actingAs($admin)
            ->get('/users/create')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertArrayHasKey('centers', $props);
        $this->assertArrayHasKey('role', $props['enums']);
        $this->assertArrayHasKey('status', $props['enums']);
        $this->assertSame($center->id, $props['centers'][0]['id']);

        // The role dropdown is built from these, so a duplicate here would render
        // as a duplicated entry in the select.
        $roles = $props['enums']['role'];
        $this->assertSame(array_keys($roles), array_unique(array_keys($roles)));
        $this->assertSame(count(UserRoleEnum::cases()), count($roles));
        $this->assertContains('agency-admin', array_keys($roles));
        $this->assertContains('executive', array_keys($roles));
        $this->assertContains('center-admin', array_keys($roles));
        $this->assertContains('reviewer', array_keys($roles));

        // Center options must be unique too, and string-valued to match the select.
        $centerIds = array_map(fn ($c) => (string) $c['id'], $props['centers']);
        $this->assertSame($centerIds, array_unique($centerIds));
    }

    public function test_the_show_page_shares_the_user(): void
    {
        $agency = $this->agency();
        $admin = $this->pccAdmin($agency);

        $user = $this->userWithRole(UserRoleEnum::EXECUTIVE, $agency);

        $props = $this->actingAs($admin)
            ->get("/users/{$user->id}")
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame($user->id, $props['user']['id']);
        $this->assertArrayNotHasKey('programs', $props['user']);
        $this->assertArrayHasKey('roles', $props['user']);
        $this->assertArrayHasKey('approvedBy', $props['user']);

        // Show renders the same form as create/edit in view mode, so it needs the
        // same reference data those pages get.
        $this->assertArrayHasKey('centers', $props);
        $this->assertArrayHasKey('role', $props['enums']);
    }

    public function test_the_edit_page_is_refused_for_a_role_without_update_users(): void
    {
        $agency = $this->agency();
        $admin = $this->pccAdmin($agency);
        $executive = $this->userWithRole(UserRoleEnum::EXECUTIVE, $agency);

        $this->actingAs($executive)->get("/users/{$executive->id}/edit")->assertForbidden();
        $this->actingAs($admin)->get("/users/{$executive->id}/edit")->assertOk();
    }

    public function test_a_center_scoped_actor_only_gets_their_own_center_as_an_option(): void
    {
        $agency = $this->agency();
        $mine = $this->center($agency, 'MINE');
        $this->center($agency, 'THEIRS');

        $encoder = $this->userWithRole(UserRoleEnum::CENTER_ADMIN, $agency, $mine);

        $props = $this->actingAs($encoder)
            ->get('/users/create')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertCount(1, $props['centers']);
        $this->assertSame($mine->id, $props['centers'][0]['id']);
    }

    public function test_the_index_can_be_filtered_by_role(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);
        $admin = $this->pccAdmin($agency);

        $this->actingAs($admin)->post('/users', $this->userPayload([
            'center_id' => $center->id,
            'username' => 'an_encoder',
            'email' => 'an.encoder@pcc.gov.ph',
        ]));
        $this->actingAs($admin)->post('/users', $this->userPayload([
            'role' => UserRoleEnum::REVIEWER->value,
            'center_id' => $center->id,
            'username' => 'a_reviewer',
            'email' => 'a.reviewer@pcc.gov.ph',
        ]));

        $emails = fn () => collect(
            $this->actingAs($admin)
                ->get('/users?filter[role]=reviewer')
                ->assertOk()
                ->viewData('page')['props']['users']['data']
        )->pluck('email');

        $this->assertTrue($emails()->contains('a.reviewer@pcc.gov.ph'));
        $this->assertFalse($emails()->contains('an.encoder@pcc.gov.ph'));
    }

    public function test_the_index_can_be_filtered_by_status(): void
    {
        $agency = $this->agency();
        $admin = $this->pccAdmin($agency);

        $this->actingAs($admin)->post('/users', $this->userPayload([
            'role' => UserRoleEnum::EXECUTIVE->value,
            'center_id' => null,
            'username' => 'pending_exec',
            'email' => 'pending.exec@pcc.gov.ph',
        ]));

        $props = $this->actingAs($admin)
            ->get('/users?filter[status]=pending')
            ->assertOk()
            ->viewData('page')['props'];

        $statuses = collect($props['users']['data'])->pluck('status');
        $this->assertTrue($statuses->contains('pending'));
        $this->assertFalse($statuses->contains('active'));
    }

    public function test_the_index_can_be_searched(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);
        $admin = $this->pccAdmin($agency);

        $this->actingAs($admin)->post('/users', $this->userPayload([
            'center_id' => $center->id,
            'name' => 'Findable Person',
            'username' => 'findable',
            'email' => 'findable@pcc.gov.ph',
        ]));

        $results = collect(
            $this->actingAs($admin)
                ->get('/users?search=Findable')
                ->assertOk()
                ->viewData('page')['props']['users']['data']
        )->pluck('email');

        $this->assertTrue($results->contains('findable@pcc.gov.ph'));
    }

    public function test_the_index_marks_pending_accounts_awaiting_a_decision(): void
    {
        // The row actions render off status, so the index has to carry it.
        $agency = $this->agency();
        $admin = $this->pccAdmin($agency);

        $this->actingAs($admin)->post('/users', $this->userPayload([
            'role' => UserRoleEnum::EXECUTIVE->value,
            'center_id' => null,
            'username' => 'row_pending',
            'email' => 'row.pending@pcc.gov.ph',
        ]));

        $row = collect(
            $this->actingAs($admin)
                ->get('/users')
                ->assertOk()
                ->viewData('page')['props']['users']['data']
        )->firstWhere('email', 'row.pending@pcc.gov.ph');

        $this->assertNotNull($row);
        $this->assertSame('pending', $row['status']);
    }

    public function test_an_approved_account_leaves_the_awaiting_set(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);
        $admin = $this->pccAdmin($agency);

        $this->actingAs($admin)->post('/users', $this->userPayload([
            'center_id' => $center->id,
            'username' => 'to_approve',
            'email' => 'to.approve@pcc.gov.ph',
        ]));

        $target = User::where('email', 'to.approve@pcc.gov.ph')->firstOrFail();

        $this->actingAs($admin)->patch("/users/{$target->id}/approve")->assertRedirect();

        $this->assertSame(
            UserStatusEnum::ACTIVE,
            User::find($target->id)->status
        );
    }

    public function test_deactivating_a_user_keeps_their_records(): void
    {
        $agency = $this->agency();
        $admin = $this->pccAdmin($agency);
        $executive = $this->userWithRole(UserRoleEnum::EXECUTIVE, $agency);

        $this->actingAs($admin)->delete("/users/{$executive->id}")->assertRedirect(route('users.index'));

        // Deactivated, never deleted: the audit trail and authored rows survive.
        $this->assertDatabaseHas('users', ['id' => $executive->id]);
        $this->assertSame(
            UserStatusEnum::DEACTIVATED,
            User::find($executive->id)->status
        );
    }
}
