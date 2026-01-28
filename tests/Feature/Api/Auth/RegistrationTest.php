<?php

namespace Tests\Feature\Api\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Domain\User\Models\User;
use App\Domain\User\Models\Role;
use Illuminate\Support\Facades\Hash;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed Roles
        Role::create(['RoleName' => 'JobSeeker']);
        Role::create(['RoleName' => 'Employer']);
    }

    public function test_user_can_register_as_job_seeker()
    {
        $response = $this->postJson('/api/auth/register', [
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '123456789',
            'role' => 'JobSeeker',
            'gender' => 'Male',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'user_id',
                    'email',
                    'name',
                    'role',
                    'token',
                ]
            ]);

        $this->assertDatabaseHas('user', ['Email' => 'john@example.com']);
        $this->assertDatabaseHas('userrole', ['UserID' => $response->json('data.user_id')]);
        $this->assertDatabaseHas('jobseekerprofile', ['JobSeekerID' => $response->json('data.user_id')]);
    }

    public function test_user_can_register_as_employer()
    {
        $response = $this->postJson('/api/auth/register', [
            'full_name' => 'Jane Boss',
            'email' => 'jane@company.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '987654321',
            'role' => 'Employer',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('companyprofile', ['CompanyID' => $response->json('data.user_id')]);
    }

    public function test_registration_validation_errors()
    {
        $response = $this->postJson('/api/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['full_name', 'email', 'password', 'phone']);
    }

    public function test_password_complexity()
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'weak@example.com',
            'password' => 'weakpass',
            'password_confirmation' => 'weakpass',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }
}
