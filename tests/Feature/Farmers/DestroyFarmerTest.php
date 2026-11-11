<?php

namespace Tests\Feature\Farmers;

use App\Models\Farmer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyFarmerTest extends TestCase
{
    use RefreshDatabase;

    public function test_destroying_a_farmer_returns_to_the_same_list_state(): void
    {
        $user = User::factory()->create(['username' => 'tester']);
        $farmer = $this->createFarmer();

        $this->actingAs($user)
            ->from(route('farmers.index', ['search' => 'jason perez', 'page' => 2]))
            ->delete(route('farmers.destroy', $farmer))
            ->assertRedirect(route('farmers.index', ['search' => 'jason perez', 'page' => 2]))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('farmers', ['id' => $farmer->id]);
    }

    public function test_destroying_a_farmer_without_a_referer_falls_back_to_the_index(): void
    {
        $user = User::factory()->create(['username' => 'tester']);
        $farmer = $this->createFarmer();

        $this->actingAs($user)
            ->delete(route('farmers.destroy', $farmer))
            ->assertRedirect(route('farmers.index'));

        $this->assertDatabaseMissing('farmers', ['id' => $farmer->id]);
    }

    private function createFarmer(): Farmer
    {
        return Farmer::query()->create([
            'code' => 'FARMER-00001',
            'first_name' => 'Jason',
            'last_name' => 'Perez',
        ]);
    }
}
