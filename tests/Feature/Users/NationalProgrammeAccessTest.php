<?php

namespace Tests\Feature\Users;

use App\Enums\UserRoleEnum;
use App\Models\EpahpGatasangKalabaw;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PL480 and EPAHP are national programmes: anyone who can read reports may open
 * them, but only the PCC administrator may change them. Export follows the usual
 * export permissions (national or own-centre), like every other report.
 */
class NationalProgrammeAccessTest extends TestCase
{
    use InteractsWithUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public static function readableRoles(): array
    {
        return [
            'pcc administrator' => [UserRoleEnum::AGENCY_ADMIN->value],
            'executive' => [UserRoleEnum::EXECUTIVE->value],
            'encoder' => [UserRoleEnum::ENCODER->value],
            'reviewer' => [UserRoleEnum::REVIEWER->value],
        ];
    }

    #[DataProvider('readableRoles')]
    public function test_any_report_reader_can_open_the_index(string $role): void
    {
        $user = $this->userWithRole(UserRoleEnum::tryFrom($role), $this->agency());

        $this->actingAs($user)->get('/pl480')->assertOk();
        $this->actingAs($user)->get('/epahp')->assertOk();
    }

    public function test_the_pcc_administrator_may_change_epahp_records(): void
    {
        $agency = $this->agency();
        $admin = $this->pccAdmin($agency);
        $center = $this->center($agency);

        $record = EpahpGatasangKalabaw::create([
            'center_id' => $center->id,
            'year' => 2026,
            'number_of_cooperatives' => 5,
            'number_of_farmer_recipients' => 20,
        ]);

        $this->actingAs($admin)
            ->put("/epahp/gatasang-kalabaw/{$record->id}", [
                'center_id' => $center->id,
                'year' => 2026,
                'number_of_cooperatives' => 9,
                'number_of_farmer_recipients' => 30,
                'number_of_animals_entrusted' => 7,
            ])
            ->assertSessionHasNoErrors();

        $record->refresh();

        $this->assertSame(9, (int) $record->number_of_cooperatives);
        $this->assertSame(30, (int) $record->number_of_farmer_recipients);
    }

    public static function nonAdminRoles(): array
    {
        return [
            'executive' => [UserRoleEnum::EXECUTIVE->value],
            'encoder' => [UserRoleEnum::ENCODER->value],
            'reviewer' => [UserRoleEnum::REVIEWER->value],
        ];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_an_executive_or_centre_role_may_not_change_epahp_records(string $role): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);
        $user = $this->userWithRole(UserRoleEnum::tryFrom($role), $agency, $center);

        $record = EpahpGatasangKalabaw::create([
            'center_id' => $center->id,
            'year' => 2026,
            'number_of_cooperatives' => 5,
        ]);

        $this->actingAs($user)
            ->put("/epahp/gatasang-kalabaw/{$record->id}", [
                'center_id' => $center->id,
                'year' => 2027,
                'number_of_cooperatives' => 99,
                'number_of_farmer_recipients' => 99,
                'number_of_animals_entrusted' => 99,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete("/epahp/gatasang-kalabaw/{$record->id}")
            ->assertForbidden();

        $this->assertSame(5, (int) $record->fresh()->number_of_cooperatives);
    }

    #[DataProvider('nonAdminRoles')]
    public function test_only_the_pcc_administrator_holds_the_national_write_permissions(string $role): void
    {
        $user = $this->userWithRole(UserRoleEnum::tryFrom($role), $this->agency());

        $this->assertFalse($user->can('manage-pl480'));
        $this->assertFalse($user->can('manage-epahp'));
        $this->assertTrue($user->can('view-reports'));
    }

    public function test_pl480_writes_are_pcc_administrator_only(): void
    {
        $executive = $this->userWithRole(UserRoleEnum::EXECUTIVE, $this->agency());

        $this->actingAs($executive)->post('/pl480/inputs', [])->assertForbidden();
        $this->actingAs($executive)->post('/pl480/inputs/bulk', [])->assertForbidden();
    }

    public function test_pl480_export_follows_the_export_permissions_not_the_write_permission(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);

        $executive = $this->userWithRole(UserRoleEnum::EXECUTIVE, $agency);
        $encoder = $this->userWithRole(UserRoleEnum::ENCODER, $agency, $center);
        $reviewer = $this->userWithRole(UserRoleEnum::REVIEWER, $agency, $center);

        $payload = ['format' => 'xlsx', 'queue_export' => true];

        $this->actingAs($executive)->post('/pl480/export', $payload)->assertOk();
        $this->actingAs($encoder)->post('/pl480/export', $payload)->assertOk();
        $this->actingAs($reviewer)->post('/pl480/export', $payload)->assertForbidden();
    }

    public function test_the_pcc_administrator_holds_both_national_write_permissions(): void
    {
        $admin = $this->pccAdmin($this->agency());

        $this->assertTrue($admin->can('manage-pl480'));
        $this->assertTrue($admin->can('manage-epahp'));
    }
}
