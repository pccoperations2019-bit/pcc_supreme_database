<?php

namespace Tests\Feature\Centers;

use App\Enums\UserRoleEnum;
use App\Models\Agency;
use App\Models\Center;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StoreCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(UserRoleEnum::AGENCY_ADMIN->value, 'web');
        Role::findOrCreate(UserRoleEnum::CENTER_ADMIN->value, 'web');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'MMSU',
            'name' => 'Mariano Marcos State University',
            'username' => 'mmsu',
            'email_address' => 'pcc-mmsu@pcc.gov.ph',
            'password' => 'RcXA3HcPwo0W',
            'password_confirmation' => 'RcXA3HcPwo0W',
            // addresses.country is NOT NULL; the form always submits it.
            'country' => 'Philippines',
        ], $overrides);
    }

    private function agencyAdmin(): User
    {
        // AgencyFactory is stale against the current schema, so build the row directly.
        $agency = Agency::create([
            'code' => 'PCC',
            'name' => 'Philippine Carabao Center',
        ]);

        $admin = User::factory()->create([
            'agency_id' => $agency->id,
            // UserFactory predates the required username column.
            'username' => 'agency-admin',
        ]);
        $admin->assignRole(UserRoleEnum::AGENCY_ADMIN->value);

        return $admin->fresh();
    }

    public function test_creating_a_center_creates_its_login_account(): void
    {
        $admin = $this->agencyAdmin();

        $response = $this->actingAs($admin)->post('/centers', $this->payload());

        $response->assertSessionHasNoErrors();

        $center = Center::where('code', 'MMSU')->firstOrFail();

        $user = User::where('email', 'pcc-mmsu@pcc.gov.ph')->firstOrFail();

        $this->assertSame($center->id, $user->center_id);
        $this->assertSame($center->agency_id, $user->agency_id);
        $this->assertSame('mmsu', $user->username);
        $this->assertTrue($user->hasRole(UserRoleEnum::CENTER_ADMIN->value));
        $this->assertTrue(Hash::check('RcXA3HcPwo0W', $user->password));
    }

    public function test_password_confirmation_must_match(): void
    {
        $admin = $this->agencyAdmin();

        $response = $this->actingAs($admin)->post('/centers', $this->payload([
            'password_confirmation' => 'something-else',
        ]));

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 1); // only the acting admin
    }

    public function test_password_and_email_are_required(): void
    {
        $admin = $this->agencyAdmin();

        $response = $this->actingAs($admin)->post('/centers', [
            'code' => 'MMSU',
            'name' => 'Mariano Marcos State University',
        ]);

        $response->assertSessionHasErrors(['password', 'email_address']);
    }

    public function test_username_defaults_to_the_lowercased_center_code_when_omitted(): void
    {
        $admin = $this->agencyAdmin();

        $payload = $this->payload();
        unset($payload['username']);

        $this->actingAs($admin)->post('/centers', $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'pcc-mmsu@pcc.gov.ph',
            'username' => 'mmsu',
        ]);
    }

    public function test_a_derived_username_that_collides_is_reported_on_the_username_field(): void
    {
        $admin = $this->agencyAdmin();
        User::factory()->create(['username' => 'mmsu']);

        $payload = $this->payload();
        unset($payload['username']);

        $response = $this->actingAs($admin)->post('/centers', $payload);

        $response->assertSessionHasErrors('username');
        $this->assertDatabaseCount('centers', 0);
    }

    public function test_center_code_longer_than_the_column_is_rejected(): void
    {
        $admin = $this->agencyAdmin();

        // centers.code is char(12); this used to reach the DB and throw a 500.
        $response = $this->actingAs($admin)->post('/centers', $this->payload([
            'code' => 'MIL-DAI-00001',
        ]));

        $response->assertSessionHasErrors('code');
        $this->assertDatabaseMissing('centers', ['code' => 'MIL-DAI-00001']);
    }

    public function test_center_code_at_the_column_limit_is_accepted(): void
    {
        $admin = $this->agencyAdmin();

        $this->actingAs($admin)->post('/centers', $this->payload([
            'code' => 'MIL-DAI-0001',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('centers', ['code' => 'MIL-DAI-0001']);
    }

    public function test_email_address_must_be_unique_across_users(): void
    {
        $admin = $this->agencyAdmin();

        $response = $this->actingAs($admin)->post('/centers', $this->payload([
            'email_address' => $admin->email,
        ]));

        $response->assertSessionHasErrors('email_address');
    }

    public function test_username_must_be_unique_across_users(): void
    {
        $admin = $this->agencyAdmin();
        User::factory()->create(['username' => 'taken']);

        $response = $this->actingAs($admin)->post('/centers', $this->payload([
            'username' => 'taken',
        ]));

        $response->assertSessionHasErrors('username');
    }
}
