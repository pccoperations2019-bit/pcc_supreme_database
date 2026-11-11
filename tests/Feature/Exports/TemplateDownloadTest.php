<?php

namespace Tests\Feature\Exports;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Users\InteractsWithUsers;
use Tests\TestCase;

class TemplateDownloadTest extends TestCase
{
    use InteractsWithUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_templates_are_downloadable_by_any_center_account(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);

        $executive = $this->userWithRole(UserRoleEnum::EXECUTIVE, $agency);
        $centerAdmin = $this->userWithRole(UserRoleEnum::CENTER_ADMIN, $agency, $center);
        $encoder = $this->userWithRole(UserRoleEnum::ENCODER, $agency, $center);
        $reviewer = $this->userWithRole(UserRoleEnum::REVIEWER, $agency, $center);

        // Report readers get past the gate and fail on the unknown key.
        foreach ([$executive, $centerAdmin, $encoder, $reviewer] as $user) {
            $this->actingAs($user)
                ->get('/reports/template-download?key=missing')
                ->assertNotFound();
        }

        $outsider = User::factory()->create(['agency_id' => $agency->id]);

        $this->actingAs($outsider)
            ->get('/reports/template-download?key=missing')
            ->assertForbidden();
    }
}
