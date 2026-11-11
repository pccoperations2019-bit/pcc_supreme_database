<?php

namespace Tests\Feature;

use App\Enums\DairyBoxEnum;
use App\Models\Agency;
use App\Models\Center;
use App\Models\DairyBox;
use App\Models\Partner;
use App\Models\Project;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class FundSourceProjectInvolvementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(Authenticate::class);
    }

    public function test_fund_sources_are_stored_in_the_canonical_form(): void
    {
        $dairyBox = $this->dairyBox();

        $dairyBox->update([
            'fund_source_outlet' => 'CBIN 2019',
            'fund_source_equipment' => 'CBIN  2021',
            'fund_source_processing_plant' => 'CBIN-2019',
        ]);

        $dairyBox->refresh();

        $this->assertSame('CBIN-2019', $dairyBox->fund_source_outlet);
        $this->assertSame('CBIN-2021', $dairyBox->fund_source_equipment);
        $this->assertSame('CBIN-2019', $dairyBox->fund_source_processing_plant);

        $this->assertDatabaseHas('dairy_boxes', [
            'id' => $dairyBox->id,
            'fund_source_outlet' => 'CBIN-2019',
            'fund_source_equipment' => 'CBIN-2021',
            'fund_source_processing_plant' => 'CBIN-2019',
        ]);
    }

    public function test_blank_fund_sources_are_stored_as_null(): void
    {
        $dairyBox = $this->dairyBox();

        $dairyBox->update(['fund_source_outlet' => '   ']);

        $this->assertNull($dairyBox->refresh()->fund_source_outlet);
    }

    public function test_updating_a_dairy_box_normalizes_submitted_fund_sources(): void
    {
        $dairyBox = $this->dairyBox();
        [$center, $partner] = $this->centerAndPartner();

        $response = $this->put(route('dairy-boxes.update', $dairyBox), [
            'dairy_box_id' => $dairyBox->dairy_box_id,
            'business_name' => $dairyBox->business_name,
            'center_id' => $center->id,
            'partner_id' => $partner->id,
            'status' => DairyBoxEnum::DEFAULT->value,
            'fund_source_outlet' => 'CBIN 2019',
            'fund_source_equipment' => 'CBIN 2021',
            'fund_source_processing_plant' => 'CBIN 2019',
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('dairy_boxes', [
            'id' => $dairyBox->id,
            'fund_source_outlet' => 'CBIN-2019',
            'fund_source_equipment' => 'CBIN-2021',
            'fund_source_processing_plant' => 'CBIN-2019',
        ]);
    }

    /**
     * The show page renders fund sources in read-only mode, but it previously
     * received no involvement options, so values were echoed unresolved.
     */
    public function test_the_show_page_receives_the_project_involvement_options(): void
    {
        $this->projects();

        $response = $this->get(route('dairy-boxes.show', $this->dairyBox()));

        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('DairyBoxes/Show')
                ->has('projectInvolvements')
                ->where('projectInvolvements', [
                    'CBED',
                    'CBED-2020',
                    'CBIN',
                    'CBIN-2020',
                    'CBIN-2021',
                ])
        );
    }

    public function test_the_edit_page_receives_the_project_involvement_options(): void
    {
        $this->projects();

        $response = $this->get(route('dairy-boxes.edit', $this->dairyBox()));

        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('DairyBoxes/Edit')
                ->has('projectInvolvements')
                ->where('projectInvolvements', [
                    'CBED',
                    'CBED-2020',
                    'CBIN',
                    'CBIN-2020',
                    'CBIN-2021',
                ])
        );
    }

    public function test_involvement_options_are_canonicalized_and_deduplicated(): void
    {
        $this->projects();

        Project::query()->create([
            'name' => 'Legacy Tag Project',
            'project_involvement' => ['CBIN 2019', 'CBIN-2019', 'CBIN-2021'],
        ]);

        $response = $this->get(route('dairy-boxes.edit', $this->dairyBox()));

        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('projectInvolvements', [
                    'CBED',
                    'CBED-2020',
                    'CBIN',
                    'CBIN-2019',
                    'CBIN-2020',
                    'CBIN-2021',
                ])
        );
    }

    /**
     * The index query did not select project_involvement, so the column always
     * rendered "N/A".
     */
    public function test_the_projects_index_includes_project_involvement_and_description(): void
    {
        Project::query()->create([
            'name' => 'Dairy Box Program',
            'description' => 'Funds dairy box equipment and outlets.',
            'project_involvement' => ['CBIN-2021', 'CBIN-2022'],
        ]);

        $response = $this->get(route('projects.index'));

        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Projects/Index')
                ->has('projects.data', 1)
                ->where('projects.data.0.name', 'Dairy Box Program')
                ->where('projects.data.0.description', 'Funds dairy box equipment and outlets.')
                ->where('projects.data.0.project_involvement', ['CBIN-2021', 'CBIN-2022'])
        );
    }

    private function projects(): void
    {
        Project::query()->create([
            'name' => 'CBIN Program',
            'project_involvement' => ['CBIN-2020', 'CBIN-2021', 'CBIN'],
        ]);

        Project::query()->create([
            'name' => 'CBED Program',
            'project_involvement' => ['CBED-2020', 'CBED'],
        ]);
    }

    private function dairyBox(): DairyBox
    {
        [$center, $partner] = $this->centerAndPartner();

        return DairyBox::query()->create([
            'dairy_box_id' => 'DB-TEST-01',
            'business_name' => 'Test Dairy Box',
            'center_id' => $center->id,
            'partner_id' => $partner->id,
        ]);
    }

    /**
     * @return array{0: Center, 1: Partner}
     */
    private function centerAndPartner(): array
    {
        $agency = Agency::query()->create([
            'code' => 'AG-'.str()->random(5),
            'name' => 'Test Agency',
        ]);

        $center = Center::query()->create([
            'agency_id' => $agency->id,
            'code' => 'CT-'.str()->random(5),
            'name' => 'Test Center',
        ]);

        $partner = Partner::query()->create([
            'center_id' => $center->id,
            'name' => 'Test Partner',
        ]);

        return [$center, $partner];
    }
}
