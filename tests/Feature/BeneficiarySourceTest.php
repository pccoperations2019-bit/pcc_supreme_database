<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Models\BeneficiarySource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Users\InteractsWithUsers;
use Tests\TestCase;

class BeneficiarySourceTest extends TestCase
{
    use InteractsWithUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['code' => 'depEd', 'name' => 'Department of Education', 'year' => 2026, 'amount' => 100], $overrides);
    }

    public function test_an_editor_can_create_read_update_and_delete_a_source(): void
    {
        $this->actingAs($this->pccAdmin());
        $this->get(route('beneficiary-sources.create'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('BeneficiarySources/Create'));
        $this->post(route('beneficiary-sources.store'), $this->payload(['code' => ' DEPED ']))->assertSessionHasNoErrors()->assertRedirect(route('beneficiary-sources.index'));
        $source = BeneficiarySource::firstOrFail();
        $this->assertSame('depEd', $source->code);
        $this->assertSame('Department of Education (DepEd)', $source->name);
        $this->get(route('beneficiary-sources.show', $source))->assertOk()->assertInertia(fn (Assert $page) => $page->component('BeneficiarySources/Show')->where('source.amount', 100));
        $this->get(route('beneficiary-sources.edit', $source))->assertOk()->assertInertia(fn (Assert $page) => $page->component('BeneficiarySources/Edit'));
        $this->put(route('beneficiary-sources.update', $source), $this->payload(['amount' => 0]))->assertSessionHasNoErrors()->assertRedirect(route('beneficiary-sources.index'));
        $this->assertDatabaseHas('beneficiary_sources', ['id' => $source->id, 'amount' => 0]);
        $this->delete(route('beneficiary-sources.destroy', $source))->assertRedirect(route('beneficiary-sources.index'));
        $this->assertDatabaseMissing('beneficiary_sources', ['id' => $source->id]);
    }

    public function test_duplicates_are_rejected_on_create_and_update_but_other_years_are_allowed(): void
    {
        $this->actingAs($this->pccAdmin());
        BeneficiarySource::create($this->payload());
        $this->post(route('beneficiary-sources.store'), $this->payload(['code' => 'deped']))->assertSessionHasErrors('code');
        $this->post(route('beneficiary-sources.store'), $this->payload(['year' => 2025]))->assertSessionHasNoErrors();
        $other = BeneficiarySource::where('year', 2025)->firstOrFail();
        $this->put(route('beneficiary-sources.update', $other), $this->payload())->assertSessionHasErrors('code');
        $this->assertSame(2025, $other->fresh()->year);
    }

    public function test_invalid_values_are_rejected(): void
    {
        $this->actingAs($this->pccAdmin());
        $this->post(route('beneficiary-sources.store'), $this->payload(['code' => str_repeat('x', 13), 'name' => ' ', 'year' => 1999, 'amount' => -1]))->assertSessionHasErrors(['code', 'name', 'year', 'amount']);
        $this->post(route('beneficiary-sources.store'), $this->payload(['amount' => 1.5]))->assertSessionHasErrors('amount');
        $this->post(route('beneficiary-sources.store'), $this->payload(['code' => 'custom']))->assertSessionHasErrors('code');
        $this->assertDatabaseCount('beneficiary_sources', 0);
    }

    public function test_year_filter_and_pagination_keep_the_selected_year(): void
    {
        $this->actingAs($this->pccAdmin());
        for ($i = 0; $i < 16; $i++) {
            BeneficiarySource::create($this->payload(['code' => 'source'.$i]));
        }
        BeneficiarySource::create($this->payload(['year' => 2025]));
        $response = $this->get(route('beneficiary-sources.index', ['filter' => ['year' => 2026]]))->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('BeneficiarySources/Index')->has('sources.data', 15)->where('sources.total', 16)->where('filters.year', '2026')->where('sourceLabels.depEd', 'DepEd'));
        $nextUrl = $response->viewData('page')['props']['sources']['next_page_url'];
        $this->assertStringContainsString('filter%5Byear%5D=2026', $nextUrl);
        $this->get($nextUrl)->assertInertia(fn (Assert $page) => $page->has('sources.data', 1)->where('sources.data.0.year', 2026));
    }

    public function test_readers_can_view_but_cannot_write_and_guests_must_log_in(): void
    {
        $source = BeneficiarySource::create($this->payload());
        $this->get(route('beneficiary-sources.index'))->assertRedirect(route('login'));
        $this->actingAs($this->userWithRole(UserRoleEnum::EXECUTIVE, $this->agency()));
        $this->get(route('beneficiary-sources.index'))->assertOk();
        $this->get(route('beneficiary-sources.show', $source))->assertOk();
        $this->get(route('beneficiary-sources.create'))->assertForbidden();
        $this->get(route('beneficiary-sources.edit', $source))->assertForbidden();
        $this->post(route('beneficiary-sources.store'), $this->payload())->assertForbidden();
        $this->put(route('beneficiary-sources.update', $source), $this->payload())->assertForbidden();
        $this->delete(route('beneficiary-sources.destroy', $source))->assertForbidden();
        $this->assertDatabaseCount('beneficiary_sources', 1);
    }

    public function test_center_accounts_cannot_access_beneficiary_sources(): void
    {
        $agency = $this->agency();
        $center = $this->center($agency);
        $source = BeneficiarySource::create($this->payload());

        foreach ([UserRoleEnum::ENCODER, UserRoleEnum::CENTER_ADMIN, UserRoleEnum::REVIEWER] as $role) {
            $user = $this->userWithRole($role, $agency, $center);
            $this->actingAs($user);
            $this->get(route('beneficiary-sources.index'))->assertForbidden();
            $this->get(route('beneficiary-sources.show', $source))->assertForbidden();
            $this->get(route('beneficiary-sources.create'))->assertForbidden();
            $this->get(route('beneficiary-sources.edit', $source))->assertForbidden();
            $this->post(route('beneficiary-sources.store'), $this->payload())->assertForbidden();
            $this->put(route('beneficiary-sources.update', $source), $this->payload())->assertForbidden();
            $this->delete(route('beneficiary-sources.destroy', $source))->assertForbidden();

            $props = $this->get(route('profile.edit'))->assertOk()->viewData('page')['props'];
            $this->assertNotContains('view-beneficiary-sources', $props['permissions']);
            $this->assertNotContains('manage-beneficiary-sources', $props['permissions']);
        }
        $this->assertDatabaseCount('beneficiary_sources', 1);
    }

    public function test_an_agency_encoder_can_manage_beneficiary_sources(): void
    {
        $this->actingAs($this->userWithRole(UserRoleEnum::ENCODER, $this->agency()));
        $response = $this->get(route('beneficiary-sources.index'))->assertOk();
        $permissions = $response->viewData('page')['props']['permissions'];
        $this->assertContains('view-beneficiary-sources', $permissions);
        $this->assertContains('manage-beneficiary-sources', $permissions);
        $this->get(route('beneficiary-sources.create'))->assertOk();
        $this->post(route('beneficiary-sources.store'), $this->payload())->assertSessionHasNoErrors();
        $source = BeneficiarySource::firstOrFail();
        $this->get(route('beneficiary-sources.edit', $source))->assertOk();
        $this->put(route('beneficiary-sources.update', $source), $this->payload(['amount' => 200]))->assertSessionHasNoErrors();
        $this->assertSame(200, $source->fresh()->amount);
        $this->delete(route('beneficiary-sources.destroy', $source))->assertRedirect(route('beneficiary-sources.index'));
        $this->assertDatabaseCount('beneficiary_sources', 0);
    }
}
