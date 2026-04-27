<?php

namespace Tests\Feature\Api\Job;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domain\User\Models\User;
use App\Domain\User\Models\Role;
use Illuminate\Support\Facades\DB;

class JobAdTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['RoleName' => 'Employer']);
        Role::create(['RoleName' => 'JobSeeker']);
    }

    public function test_employer_can_post_job()
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('RoleName', 'Employer')->first());
        DB::table('companyprofile')->insert(['CompanyID' => $user->UserID]);

        $expiryDate = now()->addDays(7)->format('Y-m-d H:i:s');

        $response = $this->actingAs($user)->postJson('/api/employer/jobs', [
            'title' => 'Backend Developer',
            'description' => 'PHP Laravel',
            'location' => 'Remote',
            'work_type' => 'Full-time',
            'salary_min' => 1000,
            'salary_max' => 2000,
            'expiry_date' => $expiryDate,
        ]);

        if ($response->status() !== 201) {
            dump($response->json());
        }

        $response->assertStatus(201)
            ->assertJson(['message' => 'Job created successfully']);

        $this->assertDatabaseHas('jobad', [
            'CompanyID' => $user->UserID,
            'Title' => 'Backend Developer',
            'ExpiryDate' => $expiryDate,
        ]);
    }

    public function test_public_can_search_jobs()
    {
        // Seeding
        $employer = User::factory()->create();
        $employer->roles()->attach(Role::where('RoleName', 'Employer')->first());
        DB::table('companyprofile')->insert(['CompanyID' => $employer->UserID]);

        DB::table('jobad')->insert([
            'CompanyID' => $employer->UserID,
            'Title' => 'Frontend Developer',
            'Description' => 'React',
            'Location' => 'Sana\'a',
            'Status' => 'Active',
        ]);

        $response = $this->getJson('/api/jobs?keyword=Frontend');

        $response->assertStatus(200)
            ->assertJsonFragment(['Title' => 'Frontend Developer']);
    }

    public function test_job_details_can_be_viewed()
    {
        $employer = User::factory()->create();
        DB::table('companyprofile')->insert(['CompanyID' => $employer->UserID]);

        $jobId = DB::table('jobad')->insertGetId([
            'CompanyID' => $employer->UserID,
            'Title' => 'Data Scientist',
            'Status' => 'Active',
            'PostedAt' => now(),
        ]);

        $response = $this->getJson("/api/jobs/{$jobId}");

        $response->assertStatus(200)
            ->assertJson(['data' => ['Title' => 'Data Scientist']]);
    }

    public function test_employer_can_close_job()
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('RoleName', 'Employer')->first());
        DB::table('companyprofile')->insert(['CompanyID' => $user->UserID]);

        $jobId = DB::table('jobad')->insertGetId([
            'CompanyID' => $user->UserID,
            'Title' => 'Data Scientist',
            'Status' => 'Active',
        ]);

        $response = $this->actingAs($user)->postJson("/api/employer/jobs/{$jobId}/close");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Job closed successfully']);

        $this->assertDatabaseHas('jobad', [
            'JobAdID' => $jobId,
            'Status' => 'Closed',
        ]);
    }

    public function test_employer_can_soft_delete_job()
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('RoleName', 'Employer')->first());
        DB::table('companyprofile')->insert(['CompanyID' => $user->UserID]);

        $jobId = DB::table('jobad')->insertGetId([
            'CompanyID' => $user->UserID,
            'Title' => 'Temporary Job',
            'Status' => 'Active',
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/employer/jobs/{$jobId}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Job deleted successfully']);

        // Assert it is still in the database (soft deleted)
        $this->assertDatabaseHas('jobad', [
            'JobAdID' => $jobId,
        ]);
        
        // Assert deleted_at is not null
        $job = DB::table('jobad')->where('JobAdID', $jobId)->first();
        $this->assertNotNull($job->deleted_at);
    }

    public function test_job_seeker_can_get_suggested_jobs()
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('RoleName', 'JobSeeker')->first());
        DB::table('jobseekerprofile')->insert(['JobSeekerID' => $user->UserID]);

        $cvId = DB::table('cv')->insertGetId([
            'JobSeekerID' => $user->UserID,
            'Title' => 'My CV',
            'CreatedAt' => now(),
        ]);

        $skillId = DB::table('skill')->insertGetId(['SkillName' => 'Laravel']);
        DB::table('cvskill')->insert(['CVID' => $cvId, 'SkillID' => $skillId, 'SkillLevel' => 'Expert']);

        // Create a matching job
        $employer = User::factory()->create();
        DB::table('companyprofile')->insert([
            'CompanyID' => $employer->UserID,
            'CompanyName' => 'Test Company'
        ]);
        
        $jobId = DB::table('jobad')->insertGetId([
            'CompanyID' => $employer->UserID,
            'Title' => 'Laravel Dev',
            'Status' => 'Active',
            'PostedAt' => now(),
        ]);
        DB::table('jobskill')->insert(['JobAdID' => $jobId, 'SkillID' => $skillId]);

        $response = $this->actingAs($user)->getJson('/api/jobs/suggested');

        $response->assertStatus(200)
            ->assertJsonFragment(['Title' => 'Laravel Dev']);
    }
}
