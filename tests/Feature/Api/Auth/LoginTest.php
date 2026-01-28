<?php

namespace Tests\Feature\Api\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Domain\User\Models\Role;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['RoleName' => 'JobSeeker']);
    }

    public function test_user_can_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'Email' => 'test@example.com',
            'PasswordHash' => Hash::make('Password123!'),
            'IsVerified' => true,
        ]);

        // Attach Role
        $role = Role::first();
        $user->roles()->attach($role->RoleID, ['AssignedAt' => now()]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'token',
                    'user_id'
                ]
            ]);
    }

    public function test_user_cannot_login_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'Email' => 'test@example.com',
            'PasswordHash' => Hash::make('Password123!'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401);
    }

    public function test_unverified_user_cannot_login()
    {
        $user = User::factory()->create([
            'Email' => 'unverified@example.com',
            'PasswordHash' => Hash::make('Password123!'),
            'IsVerified' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'unverified@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(403)
            ->assertJson(['requires_verification' => true]);
    }
}
