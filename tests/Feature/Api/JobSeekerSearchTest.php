<?php

namespace Tests\Feature\Api;

use App\Domain\User\Models\User;
use App\Domain\User\Models\JobSeekerProfile;
use App\Domain\User\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobSeekerSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure JobSeeker role exists
        Role::firstOrCreate(['RoleID' => 1, 'RoleName' => 'JobSeeker']);
    }

    public function test_can_search_job_seekers_by_name(): void
    {
        $user1 = User::factory()->create(['FullName' => 'Ahmed Ali']);
        $user2 = User::factory()->create(['FullName' => 'Sara Smith']);

        JobSeekerProfile::create(['JobSeekerID' => $user1->UserID, 'Location' => 'Riyadh']);
        JobSeekerProfile::create(['JobSeekerID' => $user2->UserID, 'Location' => 'Dubai']);

        // Register them as JobSeekers in userrole table if needed by query, 
        // but our controller query uses JobSeekerProfile directly.

        $response = $this->actingAs($user1)->getJson('/api/job-seekers?search=Ahmed');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user.FullName', 'Ahmed Ali');
    }

    public function test_can_filter_job_seekers_by_location(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        JobSeekerProfile::create(['JobSeekerID' => $user1->UserID, 'Location' => 'Riyadh']);
        JobSeekerProfile::create(['JobSeekerID' => $user2->UserID, 'Location' => 'Jeddah']);

        $response = $this->actingAs($user1)->getJson('/api/job-seekers?location=Riyadh');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.Location', 'Riyadh');
    }

    public function test_pagination_is_supported(): void
    {
        $user = User::factory()->create();
        
        for ($i = 0; $i < 20; $i++) {
            $u = User::factory()->create();
            JobSeekerProfile::create(['JobSeekerID' => $u->UserID]);
        }

        $response = $this->actingAs($user)->getJson('/api/job-seekers?per_page=10');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure(['data', 'current_page', 'last_page', 'total']);
    }
}
