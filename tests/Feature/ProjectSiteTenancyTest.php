<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Center;
use App\Models\Project;
use App\Models\ProjectSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectSiteTenancyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('center-admin', 'web');
        Role::findOrCreate('agency-admin', 'web');
    }

    /**
     * AgencyFactory is stale (writes agency_code/slug/total_* against a table
     * that only has `code`), so centers are seeded by hand here.
     */
    private function center(string $code): Center
    {
        $agency = Agency::firstOrCreate(['code' => 'AG'.strtoupper(substr($code, 0, 6))], ['name' => $code.' agency']);

        return Center::create([
            'agency_id' => $agency->id,
            'code' => $code,
            'name' => $code.' Center',
        ]);
    }

    private function cbinSiteFor(int $centerId, string $code): ProjectSite
    {
        // One shared project: the site code must not appear in any prop other
        // than the paginated rows, or assertDontSee would be meaningless.
        $project = Project::firstOrCreate(
            ['program' => 'cbin'],
            ['name' => 'CBIN Program', 'is_funded' => 1],
        );

        return ProjectSite::create([
            'project_id' => $project->id,
            'center_id' => $centerId,
            'project_site_code' => $code,
        ]);
    }

    public function test_center_admin_sees_only_own_center_project_sites(): void
    {
        $centerA = $this->center('CLSU');
        $centerB = $this->center('DMMMSU');

        $mine = $this->cbinSiteFor($centerA->id, 'CBIN-2026-0001');
        $theirs = $this->cbinSiteFor($centerB->id, 'CBIN-2026-0002');

        $user = User::factory()->create(['center_id' => $centerA->id]);
        $user->assignRole('center-admin');

        $this->actingAs($user)->get('/cbin-project-sites')
            ->assertOk()
            ->assertSee($mine->project_site_code)
            ->assertDontSee($theirs->project_site_code);

        // Cross-tenant access by URL must be refused.
        $this->actingAs($user)->get("/cbin-project-sites/{$theirs->id}")->assertForbidden();
        $this->actingAs($user)->get("/cbin-project-sites/{$theirs->id}/edit")->assertForbidden();
        $this->actingAs($user)->put("/cbin-project-sites/{$theirs->id}", [])->assertForbidden();
        $this->actingAs($user)->delete("/cbin-project-sites/{$theirs->id}")->assertForbidden();

        // A center admin can create, but only for their own center: the form
        // hides the Center field and the FormRequest pins center_id regardless
        // of what the client submits.
        $props = $this->actingAs($user)
            ->get('/cbin-project-sites/create')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertTrue($props['disableCenterField']);
        $this->assertSame($centerA->id, $props['defaultCenterId']);
        $this->assertSame([$centerA->id], array_column($props['centers'], 'id'));

        $this->actingAs($user)->post('/cbin-project-sites', [
            'project_site_code' => 'CBIN-2026-0003',
            'center_id' => $centerB->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('project_sites', [
            'project_site_code' => 'CBIN-2026-0003',
            'center_id' => $centerA->id,
        ]);
    }

    public function test_agency_admin_sees_every_project_site(): void
    {
        $centerA = $this->center('CLSU');
        $centerB = $this->center('DMMMSU');

        $mine = $this->cbinSiteFor($centerA->id, 'CBIN-2026-0001');
        $theirs = $this->cbinSiteFor($centerB->id, 'CBIN-2026-0002');

        $user = User::factory()->create(['center_id' => null]);
        $user->assignRole('agency-admin');

        $this->actingAs($user)->get('/cbin-project-sites')
            ->assertOk()
            ->assertSee($mine->project_site_code)
            ->assertSee($theirs->project_site_code);

        $this->actingAs($user)->get("/cbin-project-sites/{$theirs->id}")->assertOk();
        $this->actingAs($user)->get("/cbin-project-sites/{$theirs->id}/edit")->assertOk();
    }

    public function test_ccdp_index_is_scoped_too(): void
    {
        $centerA = $this->center('CLSU');
        $centerB = $this->center('DMMMSU');

        $project = Project::create(['program' => 'ccdp', 'name' => 'CCDP', 'is_funded' => 1]);

        $mine = ProjectSite::create(['project_id' => $project->id, 'center_id' => $centerA->id, 'project_site_code' => 'CCDP-2026-0001']);
        $theirs = ProjectSite::create(['project_id' => $project->id, 'center_id' => $centerB->id, 'project_site_code' => 'CCDP-2026-0002']);

        $user = User::factory()->create(['center_id' => $centerA->id]);
        $user->assignRole('center-admin');

        $this->actingAs($user)->get('/ccdp-project-sites')
            ->assertOk()
            ->assertSee($mine->project_site_code)
            ->assertDontSee($theirs->project_site_code);
    }
}
