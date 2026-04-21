<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_accessible_to_guest(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_register_page_accessible_to_guest(): void
    {
        $response = $this->get('/register');
        $response->assertStatus(200);
    }

    public function test_board_redirects_guest_to_login(): void
    {
        $response = $this->get('/board');
        $response->assertRedirect('/login');
    }

    public function test_done_redirects_guest_to_login(): void
    {
        $response = $this->get('/done');
        $response->assertRedirect('/login');
    }

    public function test_analytics_redirects_guest_to_login(): void
    {
        $response = $this->get('/analytics');
        $response->assertRedirect('/login');
    }

    public function test_user_can_register(): void
    {
        $response = $this->post('/register', [
            'email' => 'newuser@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect('/board');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    public function test_register_requires_email(): void
    {
        $response = $this->post('/register', [
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_register_requires_password_confirmation(): void
    {
        $response = $this->post('/register', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'wrong',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->post('/register', [
            'email' => 'existing@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $response = $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect('/board');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('correct_password'),
        ]);

        $response = $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'wrong_password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_access_board(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/board');

        $response->assertStatus(200);
    }

    public function test_authenticated_user_redirected_from_login(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/login');

        $response->assertRedirect('/board');
    }
}
