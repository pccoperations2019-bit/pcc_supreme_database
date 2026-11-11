<?php

namespace Tests\Feature\Centers;

use App\Enums\UserRoleEnum;
use App\Models\Agency;
use App\Models\Center;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Users\InteractsWithUsers;
use Tests\TestCase;

/**
 * The centers list is a PCC-officer page.
 *
 * CenterController::index shipped every center in the agency to any signed-in
 * account: it filtered hidden codes but never narrowed by the viewer, and never
 * asked CenterPolicy. So an encoder or reviewer pinned to one center received all
 * fifteen centers in the Inertia payload. The nav now drops the link for those
 * roles, which is convenience only — these tests pin the two server-side halves
 * that actually hold the line.
 *
 * The distinction the page turns on is the center, not the role: an encoder or
 * reviewer provisioned with no center is a PCC officer for the whole agency and
 * keeps the list, while the same roles pinned to a center are refused it.
 */
class CentersListAccessTest extends TestCase
{
    use InteractsWithUsers;
    use RefreshDatabase;

    private Agency $agency;

    private Center $mine;

    private Center $theirs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        $this->agency = $this->agency();
        $this->mine = $this->center($this->agency, 'MINE');
        $this->theirs = $this->center($this->agency, 'THEIRS');
    }

    public function test_a_center_encoder_is_refused_the_centers_list(): void
    {
        $encoder = $this->userWithRole(UserRoleEnum::ENCODER, $this->agency, $this->mine);

        $this->actingAs($encoder)->get(route('centers.index'))->assertForbidden();
    }

    public function test_a_center_reviewer_is_refused_the_centers_list(): void
    {
        $reviewer = $this->userWithRole(UserRoleEnum::REVIEWER, $this->agency, $this->mine);

        $this->actingAs($reviewer)->get(route('centers.index'))->assertForbidden();
    }

    public function test_an_agency_wide_encoder_keeps_the_centers_list(): void
    {
        // No center, so nationwide reach: the list is the whole point of the role.
        $encoder = $this->userWithRole(UserRoleEnum::ENCODER, $this->agency, null);

        $this->actingAs($encoder)->get(route('centers.index'))->assertOk();
    }

    public function test_an_agency_wide_reviewer_keeps_the_centers_list(): void
    {
        $reviewer = $this->userWithRole(UserRoleEnum::REVIEWER, $this->agency, null);

        $this->actingAs($reviewer)->get(route('centers.index'))->assertOk();
    }

    public function test_a_center_admin_keeps_the_centers_list(): void
    {
        // Left the list rather than special-cased out of it: it reaches its own
        // center directly, and the nav points it there.
        $admin = $this->userWithRole(UserRoleEnum::CENTER_ADMIN, $this->agency, $this->mine);

        $this->actingAs($admin)->get(route('centers.index'))->assertOk();
    }

    public function test_the_list_is_narrowed_to_its_own_center_for_a_center_admin(): void
    {
        // The companion to the gate above. A role that keeps the page must not
        // receive the whole agency through it, so the query narrows with the
        // viewer rather than trusting the route alone.
        $admin = $this->userWithRole(UserRoleEnum::CENTER_ADMIN, $this->agency, $this->mine);

        $codes = $this->centerCodesFor($admin);

        $this->assertSame([$this->mine->code], $codes);
        $this->assertNotContains($this->theirs->code, $codes);
    }

    public function test_a_pcc_admin_still_reads_every_center(): void
    {
        // The agency-wide read is unchanged: narrowing must not cost the PCC
        // administrator the nationwide list it is there to manage.
        $admin = $this->userWithRole(UserRoleEnum::AGENCY_ADMIN, $this->agency, null);

        $codes = $this->centerCodesFor($admin);

        $this->assertContains($this->mine->code, $codes);
        $this->assertContains($this->theirs->code, $codes);
    }

    public function test_a_center_encoder_cannot_widen_the_list_with_a_center_filter(): void
    {
        // The route and the query both have to hold, as on the audits page: a
        // hand-crafted filter naming another center must not switch the scope.
        $admin = $this->userWithRole(UserRoleEnum::CENTER_ADMIN, $this->agency, $this->mine);

        $props = $this->actingAs($admin)
            ->get(route('centers.index', ['filter' => ['center_id' => $this->theirs->id]]))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertNotContains(
            $this->theirs->code,
            array_column($props['centers'], 'code'),
        );
    }

    /**
     * @return list<string>
     */
    private function centerCodesFor(User $viewer): array
    {
        $props = $this->actingAs($viewer)
            ->get(route('centers.index'))
            ->assertOk()
            ->viewData('page')['props'];

        return array_column($props['centers'], 'code');
    }
}
