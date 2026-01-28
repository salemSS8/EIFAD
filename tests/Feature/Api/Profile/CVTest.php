<?php

namespace Tests\Feature\Api\Profile;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domain\User\Models\User;
use App\Domain\User\Models\Role;
use Illuminate\Support\Facades\DB;

class CVTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['RoleName' => 'JobSeeker']);
    }

    public function test_job_seeker_can_list_cvs()
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('RoleName', 'JobSeeker')->first());

        // Ensure profile exists because some logic might depend on it
        DB::table('jobseekerprofile')->insert(['JobSeekerID' => $user->UserID]);

        // Creating CVs is complex (File uploads, Parsing).
        // For this iteration, we check empty list creates success response.

        $response = $this->actingAs($user)->getJson('/api/cvs');

        $response->assertStatus(200);
        // Assert structure if possible, usually Paginated or collection
    }
}
