<?php

namespace Tests\Feature\Users;

use App\Enums\UserRoleEnum;
use App\Models\Agency;
use App\Models\Center;
use App\Models\Farmer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every surface that narrows by center must narrow for the Encoder too.
 *
 * These controllers branch on `User::isCenterScoped()`. Before that, each one
 * asked "do you have the center-admin role?", which the Encoder does not — so it
 * fell through to the agency-wide branch and saw the whole country. There are
 * around sixty such checks, so this covers the representative surfaces by route
 * rather than trusting each one.
 */
class EncoderDataScopeTest extends TestCase
{
    use InteractsWithUsers;
    use RefreshDatabase;

    protected Center $mine;

    protected Center $theirs;

    protected User $encoder;

    protected Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        $this->agency = $this->agency();
        $this->mine = $this->center($this->agency, 'MINE');
        $this->theirs = $this->center($this->agency, 'THEIRS');
        $this->encoder = $this->userWithRole(UserRoleEnum::ENCODER, $this->agency, $this->mine);
    }

    /**
     * Routes that accept an encoder and must not return national data.
     *
     * @return array<string, array{0: string}>
     */
    public static function scopedRoutes(): array
    {
        return [
            'dashboard' => ['/dashboard'],
            'profiles' => ['/profiles'],
            'farmers' => ['/farmers'],
            'partners' => ['/partners'],
            'milk feeding programs' => ['/milk-feeding-programs'],
            'center production goals' => ['/center-production-goals'],
            'center sales goals' => ['/center-sales-goals'],
            'cbin project sites' => ['/cbin-project-sites'],
            'ccdp project sites' => ['/ccdp-project-sites'],
            'pl480' => ['/pl480'],
            'epahp' => ['/epahp'],
            'documents' => ['/documents'],
        ];
    }

    #[DataProvider('scopedRoutes')]
    public function test_an_encoder_is_told_they_are_center_scoped_on_every_surface(string $uri): void
    {
        // The decisive assertion is the tenancy prop every page receives.
        // A surface still branching on the role name reports isCenterLevel=false,
        // which is what drives the agency-wide branch.
        $props = $this->actingAs($this->encoder)
            ->get($uri)
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertTrue(
            $props['tenant']['isCenterLevel'] ?? null,
            "{$uri} does not treat the encoder as center-scoped."
        );
        $this->assertFalse($props['tenant']['isAgencyLevel'] ?? null);
    }

    public function test_an_encoder_cannot_read_another_center(): void
    {
        // Cross-tenant access by URL must still be refused outright.
        $this->actingAs($this->encoder)
            ->get("/centers/{$this->theirs->id}")
            ->assertForbidden();
    }

    public function test_an_encoder_filing_a_farmer_lands_in_its_own_center(): void
    {
        // ForcesCenterForCenterScopedUser merges center_id, so the encoder's own
        // center overwrites whatever the client submitted. The record must not
        // land in the other center.
        $this->actingAs($this->encoder)
            ->post('/farmers', [
                'code' => 'SNEAK-1',
                'center_id' => $this->theirs->id,
                'first_name' => 'Sneaky',
                'last_name' => 'Person',
            ])
            ->assertSessionHasNoErrors();

        $farmer = Farmer::where('code', 'SNEAK-1')->firstOrFail();

        $this->assertSame($this->mine->id, $farmer->center_id);
        $this->assertNotSame($this->theirs->id, $farmer->center_id);
    }

    public function test_an_encoder_cannot_create_a_center(): void
    {
        // CenterPolicy::create is gated on 'manage-center', which the encoder
        // does not hold — distinct from being center-scoped.
        $this->actingAs($this->encoder)
            ->post('/centers', [
                'code' => 'NEWX',
                'name' => 'New Center',
                'username' => 'newxc',
                'email_address' => 'newxc@pcc.gov.ph',
                'password' => 'RcXA3HcPwo0W',
                'password_confirmation' => 'RcXA3HcPwo0W',
                'country' => 'Philippines',
            ])
            ->assertForbidden();
    }

    public function test_center_models_are_scoped_to_the_encoders_center(): void
    {
        // The model-level scopes the controllers lean on.
        $visible = Center::visibleTo($this->encoder)->pluck('id')->all();

        $this->assertSame([$this->mine->id], $visible);
        $this->assertNotContains($this->theirs->id, $visible);
    }

    public function test_a_center_admin_still_behaves_the_same(): void
    {
        // The replacement must not change the encoder's own behaviour.
        $admin = $this->userWithRole(UserRoleEnum::CENTER_ADMIN, $this->agency, $this->mine);

        $this->actingAs($admin)
            ->post('/centers', [
                'code' => 'NEWY',
                'name' => 'Another Center',
                'username' => 'newyc',
                'email_address' => 'newyc@pcc.gov.ph',
                'password' => 'RcXA3HcPwo0W',
                'password_confirmation' => 'RcXA3HcPwo0W',
                'country' => 'Philippines',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('centers', ['code' => 'NEWY']);
    }
}
