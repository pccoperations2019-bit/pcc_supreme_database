<?php

namespace Tests\Feature\Users;

use App\Enums\UserRoleEnum;
use App\Models\Center;
use App\Models\User;
use App\Support\Centers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NHQ is hidden in every context. GP and NIZ are program-conditional instead, so
 * they must survive on pages that are not program-scoped.
 */
class HiddenCenterVisibilityTest extends TestCase
{
    use InteractsWithUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_nhq_is_in_the_always_hidden_set(): void
    {
        $this->assertContains('nhq', Centers::alwaysHiddenCenters());
    }

    public function test_the_always_hidden_set_does_not_include_the_national_centers(): void
    {
        // hiddenCenters(null) returns [nhq, gp]; that program-conditional half must
        // not leak into pages which are not program-scoped.
        $this->assertContains('gp', Centers::hiddenCenters());
        $this->assertNotContains('gp', Centers::alwaysHiddenCenters());
        $this->assertNotContains('niz', Centers::alwaysHiddenCenters());
    }

    public function test_the_center_index_hides_nhq_but_keeps_gp(): void
    {
        $agency = $this->agency();
        $admin = $this->pccAdmin($agency);

        Center::create(['agency_id' => $agency->id, 'code' => 'NHQ', 'name' => 'NHQ']);
        Center::create(['agency_id' => $agency->id, 'code' => 'GP', 'name' => 'Genepool']);

        $codes = collect(
            $this->actingAs($admin)
                ->get('/centers')
                ->assertOk()
                ->viewData('page')['props']['centers']
        )->pluck('code');

        $this->assertFalse($codes->contains('NHQ'), 'NHQ must be hidden on the center index.');
        $this->assertTrue($codes->contains('GP'), 'GP must remain visible outside a program filter.');
    }

    public function test_the_user_list_hides_nhq_accounts(): void
    {
        $agency = $this->agency();
        $nhq = Center::create(['agency_id' => $agency->id, 'code' => 'NHQ', 'name' => 'NHQ']);
        $clsu = Center::create(['agency_id' => $agency->id, 'code' => 'CLSU', 'name' => 'CLSU']);

        $admin = $this->pccAdmin($agency);
        $this->actingAs($admin)->post('/users', $this->userPayload([
            'center_id' => $nhq->id,
            'username' => 'nhq_admin',
            'email' => 'nhq.admin@pcc.gov.ph',
        ]));
        $this->actingAs($admin)->post('/users', $this->userPayload([
            'center_id' => $clsu->id,
            'username' => 'clsu_admin',
            'email' => 'clsu.admin@pcc.gov.ph',
        ]));

        $emails = collect(
            $this->actingAs($admin)
                ->get('/users')
                ->assertOk()
                ->viewData('page')['props']['users']['data']
        )->pluck('email');

        $this->assertFalse($emails->contains('nhq.admin@pcc.gov.ph'));
        $this->assertTrue($emails->contains('clsu.admin@pcc.gov.ph'));
    }

    public function test_the_user_list_keeps_accounts_with_no_center(): void
    {
        // A PCC-wide role has no center, so a bare whereHas('center') would drop
        // every agency-level user from the list.
        $agency = $this->agency();
        $admin = $this->pccAdmin($agency);

        $executive = $this->userWithRole(UserRoleEnum::EXECUTIVE, $agency);

        $emails = collect(
            $this->actingAs($admin)
                ->get('/users')
                ->assertOk()
                ->viewData('page')['props']['users']['data']
        )->pluck('email');

        $this->assertTrue($emails->contains($executive->email));
    }

    public function test_the_create_user_center_options_hide_nhq_but_keep_the_national_centers(): void
    {
        $agency = $this->agency();
        $admin = $this->pccAdmin($agency);

        Center::create(['agency_id' => $agency->id, 'code' => 'NHQ', 'name' => 'National Headquarters']);
        Center::create(['agency_id' => $agency->id, 'code' => 'GP', 'name' => 'Genepool']);
        Center::create(['agency_id' => $agency->id, 'code' => 'CLSU', 'name' => 'Cagayan State University']);

        $codes = collect(
            $this->actingAs($admin)
                ->get('/users/create')
                ->assertOk()
                ->viewData('page')['props']['centers']
        )->pluck('code');

        $this->assertFalse($codes->contains('NHQ'), 'NHQ must not be offered on the create form.');
        $this->assertTrue($codes->contains('GP'), 'GP must remain assignable outside a program filter.');
        $this->assertTrue($codes->contains('CLSU'));
    }

    public function test_an_nhq_account_is_still_reachable_directly(): void
    {
        // Hiding it from the list must not make it unmanageable.
        $agency = $this->agency();
        $nhq = Center::create(['agency_id' => $agency->id, 'code' => 'NHQ', 'name' => 'NHQ']);

        $admin = $this->pccAdmin($agency);
        $this->actingAs($admin)->post('/users', $this->userPayload([
            'center_id' => $nhq->id,
            'username' => 'nhq_direct',
            'email' => 'nhq.direct@pcc.gov.ph',
        ]));

        $hidden = User::where('email', 'nhq.direct@pcc.gov.ph')->firstOrFail();

        $this->actingAs($admin)->get("/users/{$hidden->id}")->assertOk();
    }
}
