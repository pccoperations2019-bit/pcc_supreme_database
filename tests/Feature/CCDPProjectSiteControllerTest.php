<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Center;
use App\Models\Partner;
use App\Models\ProjectSite;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CCDPProjectSiteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(Authenticate::class);
    }

    public function test_store_normalizes_and_persists_the_processing_partner_without_mass_assigning_child_relations(): void
    {
        $center = $this->center();
        $processingPartner = $this->partner($center);

        $response = $this->post(route('ccdp-project-sites.store'), [
            'center_id' => (string) $center->id,
            'processing_partner_id' => (string) $processingPartner->id,
            'productions' => [[
                'no_of_recipients' => 12,
            ]],
        ]);

        $response->assertRedirect(route('ccdp-project-sites.index'));

        $projectSite = ProjectSite::query()->sole();

        $this->assertSame($center->id, $projectSite->center_id);
        $this->assertSame($processingPartner->id, $projectSite->processing_partner_id);
        $this->assertArrayNotHasKey('productions', $projectSite->getAttributes());
        $this->assertDatabaseHas('project_site_productions', [
            'project_site_id' => $projectSite->id,
            'no_of_recipients' => 12,
        ]);
    }

    public function test_update_persists_the_processing_partner_and_syncs_child_relations(): void
    {
        $center = $this->center();
        $processingPartner = $this->partner($center);
        $projectSite = ProjectSite::query()->create([
            'project_site_code' => 'CCDP-2026-UPDATE',
        ]);

        $response = $this->put(route('ccdp-project-sites.update', $projectSite), [
            'center_id' => $center->id,
            'processing_partner_id' => $processingPartner->id,
            'processing_infrastructure' => [[
                'type_of_ownership' => null,
            ]],
        ]);

        $response->assertRedirect(route('ccdp-project-sites.index'));

        $projectSite->refresh();

        $this->assertSame($center->id, $projectSite->center_id);
        $this->assertSame($processingPartner->id, $projectSite->processing_partner_id);
        $this->assertArrayNotHasKey('processing_infrastructure', $projectSite->getAttributes());
        $this->assertDatabaseHas('project_site_infrastructures', [
            'project_site_id' => $projectSite->id,
        ]);
    }

    public function test_edit_eager_loads_the_processing_partner(): void
    {
        $center = $this->center();
        $processingPartner = $this->partner($center);
        $projectSite = ProjectSite::query()->create([
            'project_site_code' => 'CCDP-2026-EDIT',
            'center_id' => $center->id,
            'processing_partner_id' => $processingPartner->id,
        ]);

        $response = $this->get(route('ccdp-project-sites.edit', $projectSite));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('ProjectSites/CCDP/Edit')
            ->where('projectSite.processing_partner.id', $processingPartner->id)
        );
    }

    public function test_store_rejects_a_processing_partner_from_another_center(): void
    {
        $center = $this->center();
        $processingPartner = $this->partner($this->center());

        $response = $this->from(route('ccdp-project-sites.create'))->post(route('ccdp-project-sites.store'), [
            'center_id' => $center->id,
            'processing_partner_id' => $processingPartner->id,
        ]);

        $response->assertRedirect(route('ccdp-project-sites.create'));
        $response->assertSessionHasErrors('processing_partner_id');
        $this->assertDatabaseCount('project_sites', 0);
    }

    private function center(): Center
    {
        $agency = Agency::query()->create([
            'code' => 'AG-'.str()->random(5),
            'name' => 'Test Agency',
        ]);

        return Center::query()->create([
            'agency_id' => $agency->id,
            'code' => 'CT-'.str()->random(5),
            'name' => 'Test Center',
        ]);
    }

    private function partner(Center $center): Partner
    {
        return Partner::query()->create([
            'center_id' => $center->id,
            'name' => 'Processing Partner',
        ]);
    }
}
