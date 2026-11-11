<?php

namespace Tests\Feature;

use App\Http\Requests\StoreProjectSiteRequest;
use App\Http\Requests\UpdateProjectSiteRequest;
use App\Models\ProjectSite;
use App\Services\ProjectSiteAddressService;
use App\Services\ProjectSiteRelationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ProjectSiteRelationsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_staff_nested_under_their_infrastructures(): void
    {
        $projectSite = ProjectSite::query()->create([]);

        $request = Request::create('/', 'POST', [
            'processing_infrastructure' => [[
                'type_of_ownership' => 'Cooperative',
                'processing_staff' => [[
                    'staff_name' => 'Processing Manager',
                    'contact_number' => '09171234567',
                    'number_of_staff' => 4,
                ]],
            ]],
            'marketing_infrastructure' => [[
                'marketing_staff' => [[
                    'staff_name' => 'Marketing Manager',
                    'contact_number' => '09187654321',
                    'number_of_staff' => 3,
                ]],
            ]],
            'processing_staff' => [[
                'staff_name' => 'Top-level Processing Manager',
            ]],
            'marketing_staff' => [[
                'staff_name' => 'Top-level Marketing Manager',
            ]],
        ]);

        $this->service()->sync($projectSite, $request);

        $processingInfrastructure = $projectSite->infrastructure()->firstOrFail();
        $processingStaff = $processingInfrastructure->processingStaff()->firstOrFail();
        $marketingInfrastructure = $projectSite->marketingInfrastructure()->firstOrFail();
        $marketingStaff = $marketingInfrastructure->marketingStaff()->firstOrFail();

        $this->assertSame($processingInfrastructure->id, $processingStaff->project_site_infrastructure_id);
        $this->assertSame('Processing Manager', $processingStaff->staff_name);
        $this->assertSame($marketingInfrastructure->id, $marketingStaff->project_site_marketing_infrastructure_id);
        $this->assertSame('Marketing Manager', $marketingStaff->staff_name);
        $this->assertNull($processingStaff->project_site_id);
        $this->assertNull($marketingStaff->project_site_id);
        $this->assertSame(0, $projectSite->processingStaff()->count());
        $this->assertSame(0, $projectSite->marketingStaff()->count());
    }

    public function test_it_still_persists_legacy_top_level_staff_arrays(): void
    {
        $projectSite = ProjectSite::query()->create([]);

        $request = Request::create('/', 'POST', [
            'processing_staff' => [[
                'staff_name' => 'Legacy Processing Manager',
                'number_of_staff' => 2,
            ]],
            'marketing_staff' => [[
                'staff_name' => 'Legacy Marketing Manager',
                'number_of_staff' => 1,
            ]],
        ]);

        $this->service()->sync($projectSite, $request);

        $this->assertDatabaseHas('project_site_processing_staff', [
            'project_site_id' => $projectSite->id,
            'project_site_infrastructure_id' => null,
            'staff_name' => 'Legacy Processing Manager',
        ]);
        $this->assertDatabaseHas('project_site_marketing_staff', [
            'project_site_id' => $projectSite->id,
            'project_site_marketing_infrastructure_id' => null,
            'staff_name' => 'Legacy Marketing Manager',
        ]);
    }

    public function test_store_and_update_requests_validate_nested_staff_arrays(): void
    {
        $data = [
            'processing_infrastructure' => [[
                'processing_staff' => [[
                    'staff_name' => str_repeat('a', 256),
                    'number_of_staff' => -1,
                ]],
            ]],
            'marketing_infrastructure' => [[
                'marketing_staff' => [[
                    'contact_number' => str_repeat('b', 256),
                ]],
            ]],
        ];
        $ruleSets = [
            (new StoreProjectSiteRequest)->rules(),
            (new UpdateProjectSiteRequest)->rules(),
        ];

        foreach ($ruleSets as $rules) {
            $validator = Validator::make($data, $rules);

            $this->assertTrue($validator->fails());
            $this->assertTrue($validator->errors()->has('processing_infrastructure.0.processing_staff.0.staff_name'));
            $this->assertTrue($validator->errors()->has('processing_infrastructure.0.processing_staff.0.number_of_staff'));
            $this->assertTrue($validator->errors()->has('marketing_infrastructure.0.marketing_staff.0.contact_number'));
        }
    }

    private function service(): ProjectSiteRelationsService
    {
        return new ProjectSiteRelationsService(new ProjectSiteAddressService);
    }
}
