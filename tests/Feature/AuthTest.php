<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_can_register_and_receives_a_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'user' => ['id', 'email', 'role']])
            ->assertJsonPath('user.role', 'customer');

        $this->assertDatabaseHas('users', ['email' => 'ada@example.com', 'role' => 'customer']);
    }

    public function test_registration_is_validated(): void
    {
        $this->postJson('/api/auth/register', ['name' => '', 'email' => 'not-an-email', 'password' => '123'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_a_customer_can_login_with_valid_credentials(): void
    {
        User::factory()->create(['email' => 'ada@example.com', 'password' => 'Secret123']);

        $this->postJson('/api/auth/login', ['email' => 'ada@example.com', 'password' => 'Secret123'])
            ->assertOk()
            ->assertJsonStructure(['access_token']);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'ada@example.com', 'password' => 'Secret123']);

        $this->postJson('/api/auth/login', ['email' => 'ada@example.com', 'password' => 'wrong'])
            ->assertStatus(401);
    }

    public function test_protected_routes_require_a_token(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
        $this->getJson('/api/orders')->assertStatus(401);
    }

    public function test_me_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api')->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }
}
