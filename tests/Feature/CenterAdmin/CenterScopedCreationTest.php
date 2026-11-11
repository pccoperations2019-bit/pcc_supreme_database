<?php

namespace Tests\Feature\CenterAdmin;

use App\Enums\UserRoleEnum;
use App\Models\Agency;
use App\Models\Center;
use App\Models\Farmer;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A center admin may create records, but only ever for their own center.
 *
 * The Center select is hidden from center admins in the UI, so the request
 * usually arrives without a center_id at all; when one is sent anyway it must
 * be overridden rather than trusted.
 */
class CenterScopedCreationTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private Center $ownCenter;

    private Center $otherCenter;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(UserRoleEnum::CENTER_ADMIN->value, 'web');

        $this->agency = Agency::create(['code' => 'PCC', 'name' => 'Philippine Carabao Center']);
        $this->ownCenter = $this->center('CLSU');
        $this->otherCenter = $this->center('MMSU');

        $this->admin = User::factory()->create([
            'agency_id' => $this->agency->id,
            'center_id' => $this->ownCenter->id,
            'username' => 'admin-clsu',
            'password' => Hash::make('password'),
        ]);
        $this->admin->assignRole(UserRoleEnum::CENTER_ADMIN->value);
    }

    public function test_center_admin_can_open_the_partner_create_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('partners.create'))
            ->assertOk();
    }

    public function test_center_admin_can_open_the_farmer_create_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('farmers.create'))
            ->assertOk();
    }

    public function test_partner_lands_in_the_admins_own_center_when_another_center_is_submitted(): void
    {
        $this->actingAs($this->admin)
            ->post(route('partners.store'), $this->partnerPayload([
                'center_id' => $this->otherCenter->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('partners', [
            'code' => 'PARTNER-00001',
            'center_id' => $this->ownCenter->id,
        ]);
    }

    public function test_partner_lands_in_the_admins_own_center_when_no_center_is_submitted(): void
    {
        // The Center select is hidden for center admins, so center_id is absent.
        $this->actingAs($this->admin)
            ->post(route('partners.store'), $this->partnerPayload([
                'code' => 'PARTNER-00002',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('partners', [
            'code' => 'PARTNER-00002',
            'center_id' => $this->ownCenter->id,
        ]);
    }

    public function test_partner_cannot_be_moved_to_another_center_on_update(): void
    {
        $partner = Partner::create([
            'code' => 'PARTNER-00003',
            'name' => 'Existing Partner',
            'center_id' => $this->ownCenter->id,
        ]);

        $this->actingAs($this->admin)
            ->put(route('partners.update', $partner), $this->partnerPayload([
                'center_id' => $this->otherCenter->id,
                'code' => $partner->code,
                'name' => 'Renamed Partner',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('partners', [
            'id' => $partner->id,
            'center_id' => $this->ownCenter->id,
            'name' => 'Renamed Partner',
        ]);
    }

    public function test_farmer_lands_in_the_admins_own_center_when_another_center_is_submitted(): void
    {
        $this->actingAs($this->admin)
            ->post(route('farmers.store'), $this->farmerPayload([
                'center_id' => $this->otherCenter->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('farmers', [
            'code' => 'FARMER-00001',
            'center_id' => $this->ownCenter->id,
        ]);
    }

    public function test_farmer_lands_in_the_admins_own_center_when_no_center_is_submitted(): void
    {
        $this->actingAs($this->admin)
            ->post(route('farmers.store'), $this->farmerPayload([
                'code' => 'FARMER-00002',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('farmers', [
            'code' => 'FARMER-00002',
            'center_id' => $this->ownCenter->id,
        ]);
    }

    public function test_farmer_cannot_be_linked_to_a_partner_from_another_center(): void
    {
        $foreignPartner = Partner::create([
            'code' => 'PARTNER-00004',
            'name' => 'Other Center Partner',
            'center_id' => $this->otherCenter->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('farmers.store'), $this->farmerPayload([
                'code' => 'FARMER-00003',
                'partner_id' => $foreignPartner->id,
            ]))
            ->assertSessionHasNoErrors();

        $farmer = Farmer::query()->where('code', 'FARMER-00003')->firstOrFail();

        $this->assertSame($this->ownCenter->id, $farmer->center_id);
        $this->assertNull($farmer->partner_id);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function partnerPayload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'PARTNER-00001',
            'name' => 'Jason Perez Trading',
            // addresses.country is NOT NULL; the form always submits it.
            'country' => 'Philippines',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function farmerPayload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'FARMER-00001',
            'first_name' => 'Jason',
            'last_name' => 'Perez',
        ], $overrides);
    }

    private function center(string $code): Center
    {
        return Center::create([
            'agency_id' => $this->agency->id,
            'code' => $code,
            'name' => strtoupper($code).' Center',
        ]);
    }
}
