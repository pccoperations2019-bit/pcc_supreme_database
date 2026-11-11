<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/dashboard')
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'username' => 'test-user',
            ]);

        // back() keeps the user on the page they opened the settings modal from,
        // and the success flash is what drives the toast.
        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/dashboard')
            ->assertSessionHas('success', 'Profile updated successfully.');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertSame('test-user', $user->username);
        $this->assertNull($user->email_verified_at);
    }

    public function test_username_must_be_unique(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['username' => 'taken']);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
                'username' => 'taken',
            ]);

        $response->assertSessionHasErrors('username');

        $this->assertNotSame('taken', $user->refresh()->username);
    }

    public function test_username_may_be_kept_unchanged(): void
    {
        $user = User::factory()->create(['username' => 'keeper']);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
                'username' => 'keeper',
            ]);

        $response->assertSessionHasNoErrors();

        $this->assertSame('keeper', $user->refresh()->username);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/dashboard')
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
                'username' => $user->username,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/dashboard');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            // ProfileController::destroy redirects to /profiles, not '/'.
            ->assertRedirect('/profiles');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
