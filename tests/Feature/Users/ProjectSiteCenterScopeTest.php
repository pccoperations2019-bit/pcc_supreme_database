<?php

namespace Tests\Feature\Users;

use App\Enums\UserRoleEnum;
use App\Models\Agency;
use App\Models\Center;
use App\Models\Project;
use App\Models\ProjectSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Project sites belong to a centre, so a centre-level account — encoder or
 * center admin — must only ever see and file its own.
 *
 * CBINProjectSiteController::index() had no scoping at all, and its
 * abortIfCenterAdmin() contradicted ProjectSitePolicy::create(), which allows a
 * centre to file its own site.
 */
class ProjectSiteCenterScopeTest extends TestCase
{
    use InteractsWithUsers;
    use RefreshDatabase;

    protected Agency $agency;

    protected Center $mine;

    protected Center $theirs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        $this->agency = $this->agency();
        $this->mine = $this->center($this->agency, 'CLSU');
        $this->theirs = $this->center($this->agency, 'MMSU');

        $project = Project::firstOrCreate(
            ['program' => 'cbin'],
            ['name' => 'CBIN Program', 'is_funded' => 1]
        );

        foreach ([[$this->mine, 'CBIN-2026-0001'], [$this->theirs, 'CBIN-2026-0002']] as [$center, $code]) {
            ProjectSite::create([
                'project_id' => $project->id,
                'center_id' => $center->id,
                'project_site_code' => $code,
            ]);
        }
    }

    public static function centreScopedRoles(): array
    {
        return [
            'encoder' => [UserRoleEnum::ENCODER->value],
            'center admin' => [UserRoleEnum::CENTER_ADMIN->value],
        ];
    }

    #[DataProvider('centreScopedRoles')]
    public function test_a_centre_level_account_only_lists_its_own_project_sites(string $role): void
    {
        $user = $this->userWithRole(UserRoleEnum::tryFrom($role), $this->agency, $this->mine);

        $this->actingAs($user)
            ->get('/cbin-project-sites')
            ->assertOk()
            ->assertSee('CBIN-2026-0001')
            ->assertDontSee('CBIN-2026-0002');
    }

    #[DataProvider('centreScopedRoles')]
    public function test_a_centre_level_account_cannot_open_another_centres_site(string $role): void
    {
        $user = $this->userWithRole(UserRoleEnum::tryFrom($role), $this->agency, $this->mine);
        $theirs = ProjectSite::where('project_site_code', 'CBIN-2026-0002')->firstOrFail();

        $this->actingAs($user)->get("/cbin-project-sites/{$theirs->id}")->assertForbidden();
    }

    public function test_an_encoder_may_open_the_create_screen(): void
    {
        // Was a 403: abortIfCenterAdmin() contradicted ProjectSitePolicy::create().
        $encoder = $this->userWithRole(UserRoleEnum::ENCODER, $this->agency, $this->mine);

        $this->actingAs($encoder)->get('/cbin-project-sites/create')->assertOk();
    }

    public function test_an_encoder_may_file_a_project_site_under_its_own_center(): void
    {
        $encoder = $this->userWithRole(UserRoleEnum::ENCODER, $this->agency, $this->mine);

        $response = $this->actingAs($encoder)->post('/cbin-project-sites', [
            'project_id' => Project::where('program', 'cbin')->value('id'),
            'project_site_code' => 'CBIN-2026-0003',
            'year' => 2026,
        ]);

        $this->assertNotSame(403, $response->getStatusCode(), 'the encoder was refused outright');
    }

    public function test_a_reviewer_may_not_file_a_project_site(): void
    {
        // Reviewers hold no 'enter-data', which is what filing a site now needs.
        $reviewer = $this->userWithRole(UserRoleEnum::REVIEWER, $this->agency, $this->mine);

        $this->actingAs($reviewer)->get('/cbin-project-sites/create')->assertForbidden();
    }

    public function test_an_executive_may_not_file_a_project_site(): void
    {
        $executive = $this->userWithRole(UserRoleEnum::EXECUTIVE, $this->agency);

        $this->actingAs($executive)->get('/cbin-project-sites/create')->assertForbidden();
    }

    public function test_a_pcc_administrator_sees_every_project_site(): void
    {
        $admin = $this->pccAdmin($this->agency);

        $this->actingAs($admin)
            ->get('/cbin-project-sites')
            ->assertOk()
            ->assertSee('CBIN-2026-0001')
            ->assertSee('CBIN-2026-0002');
    }

    public function test_the_ccdp_listing_is_scoped_too(): void
    {
        $project = Project::firstOrCreate(['program' => 'ccdp'], ['name' => 'CCDP Program', 'is_funded' => 1]);

        ProjectSite::create([
            'project_id' => $project->id,
            'center_id' => $this->mine->id,
            'project_site_code' => 'CCDP-2026-0001',
        ]);
        ProjectSite::create([
            'project_id' => $project->id,
            'center_id' => $this->theirs->id,
            'project_site_code' => 'CCDP-2026-0002',
        ]);

        $encoder = $this->userWithRole(UserRoleEnum::ENCODER, $this->agency, $this->mine);

        $this->actingAs($encoder)
            ->get('/ccdp-project-sites')
            ->assertOk()
            ->assertSee('CCDP-2026-0001')
            ->assertDontSee('CCDP-2026-0002');
    }
}
