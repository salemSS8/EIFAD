<?php

namespace Tests\Feature\Api\Job;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domain\User\Models\User;
use App\Domain\User\Models\Role;
use Illuminate\Support\Facades\DB;

class JobApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['RoleName' => 'Employer']);
        Role::create(['RoleName' => 'JobSeeker']);
    }

    public function test_job_seeker_can_apply_to_job()
    {
        // 1. Create Employer & Job
        $employer = User::factory()->create();
        DB::table('companyprofile')->insert(['CompanyID' => $employer->UserID]);
        $jobId = DB::table('jobad')->insertGetId([
            'CompanyID' => $employer->UserID,
            'Title' => 'Job 1',
            'Status' => 'Active',
            'PostedAt' => now(),
        ]);

        // 2. Create Job Seeker & Profile & CV
        $seeker = User::factory()->create();
        $seeker->roles()->attach(Role::where('RoleName', 'JobSeeker')->first());
        DB::table('jobseekerprofile')->insert(['JobSeekerID' => $seeker->UserID]);
        $cvId = DB::table('cv')->insertGetId([
            'JobSeekerID' => $seeker->UserID,
            'Title' => 'My CV',
            'CreatedAt' => now(),
            'UpdatedAt' => now(),
        ]);

        // 3. Apply
        $response = $this->actingAs($seeker)->postJson('/api/applications', [
            'job_id' => $jobId,
            'cv_id' => $cvId,
            'notes' => 'I am interested',
        ]);

        $response->assertStatus(201)
            ->assertJson(['message' => __('application.application_submitted')]);

        $this->assertDatabaseHas('jobapplication', [
            'JobAdID' => $jobId,
            'JobSeekerID' => $seeker->UserID,
            'CVID' => $cvId,
            'JobSeekerName' => $seeker->FullName ?? $seeker->Name,
        ]);
    }

    public function test_job_seeker_can_apply_with_pdf_resume()
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        // 1. Setup Employer & Job
        $employer = User::factory()->create();
        DB::table('companyprofile')->insert(['CompanyID' => $employer->UserID]);
        $jobId = DB::table('jobad')->insertGetId([
            'CompanyID' => $employer->UserID,
            'Title' => 'Job with PDF',
            'Status' => 'Active',
            'PostedAt' => now(),
        ]);

        // 2. Setup Seeker
        $seeker = User::factory()->create();
        $seeker->roles()->attach(Role::where('RoleName', 'JobSeeker')->first());
        DB::table('jobseekerprofile')->insert(['JobSeekerID' => $seeker->UserID]);

        // 3. Apply with PDF
        $file = \Illuminate\Http\UploadedFile::fake()->create('resume.pdf', 100);
        $response = $this->actingAs($seeker)->post('/api/applications', [
            'job_id' => $jobId,
            'cv' => $file,
            'JobSeekerName' => 'Override Name',
            'AboutMe' => 'I am an expert',
        ]);

        $response->assertStatus(201)
            ->assertJson(['message' => __('application.application_submitted')]);

        $this->assertDatabaseHas('jobapplication', [
            'JobAdID' => $jobId,
            'JobSeekerID' => $seeker->UserID,
            'CVID' => null,
            'JobSeekerName' => 'Override Name',
            'AboutMe' => 'I am an expert',
        ]);

        $application = \App\Domain\Application\Models\JobApplication::latest('ApplicationID')->first();
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($application->CV);
        $this->assertNull($application->MatchScore);
    }

    public function test_cannot_apply_twice()
    {
        // Setup
        $employer = User::factory()->create();
        DB::table('companyprofile')->insert(['CompanyID' => $employer->UserID]);
        $jobId = DB::table('jobad')->insertGetId([
            'CompanyID' => $employer->UserID,
            'Title' => 'Job 1',
            'Status' => 'Active'
        ]);

        $seeker = User::factory()->create();
        $seeker->roles()->attach(Role::where('RoleName', 'JobSeeker')->first());
        DB::table('jobseekerprofile')->insert(['JobSeekerID' => $seeker->UserID]);
        $cvId = DB::table('cv')->insertGetId(['JobSeekerID' => $seeker->UserID, 'Title' => 'CV']);

        // First application
        DB::table('jobapplication')->insert([
            'JobAdID' => $jobId,
            'JobSeekerID' => $seeker->UserID,
            'CVID' => $cvId,
            'AppliedAt' => now(),
        ]);

        // Second attempt
        $response = $this->actingAs($seeker)->postJson('/api/applications', [
            'job_id' => $jobId,
            'cv_id' => $cvId,
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => __('application.application_already_exists')]);
    }

    public function test_cannot_apply_to_expired_job()
    {
        // Employer and expired Job
        $employer = User::factory()->create();
        DB::table('companyprofile')->insert(['CompanyID' => $employer->UserID]);
        $jobId = DB::table('jobad')->insertGetId([
            'CompanyID' => $employer->UserID,
            'Title' => 'Expired Job',
            'Status' => 'Active',
            'ExpiryDate' => now()->subDays(1),
        ]);

        // Seeker
        $seeker = User::factory()->create();
        $seeker->roles()->attach(Role::where('RoleName', 'JobSeeker')->first());
        DB::table('jobseekerprofile')->insert(['JobSeekerID' => $seeker->UserID]);
        $cvId = DB::table('cv')->insertGetId(['JobSeekerID' => $seeker->UserID, 'Title' => 'CV']);

        // Attempt Apply
        $response = $this->actingAs($seeker)->postJson('/api/applications', [
            'job_id' => $jobId,
            'cv_id' => $cvId,
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'The application deadline for this job has passed']);
    }

    public function test_employer_can_view_job_applications()
    {
        // Employer and Job
        $employer = User::factory()->create();
        $employer->roles()->attach(Role::where('RoleName', 'Employer')->first());
        DB::table('companyprofile')->insert(['CompanyID' => $employer->UserID]);
        $jobId = DB::table('jobad')->insertGetId([
            'CompanyID' => $employer->UserID,
            'Title' => 'Job 1',
            'Status' => 'Active'
        ]);

        // Application
        $seeker = User::factory()->create();
        DB::table('jobseekerprofile')->insert(['JobSeekerID' => $seeker->UserID]);
        $cvId = DB::table('cv')->insertGetId(['JobSeekerID' => $seeker->UserID, 'Title' => 'CV']);

        DB::table('jobapplication')->insert([
            'JobAdID' => $jobId,
            'JobSeekerID' => $seeker->UserID,
            'CVID' => $cvId,
            'AppliedAt' => now(),
        ]);

        $response = $this->actingAs($employer)->getJson("/api/employer/jobs/{$jobId}/applications");

        if ($response->status() !== 200) {
            dump($response->json());
        } else {
            $data = $response->json('data');
            if (empty($data) || is_null($data[0]['job_seeker'])) {
                dump("JobSeeker is null. App ID:", $data[0]['ApplicationID']);
                dump("App JobSeekerID:", $data[0]['JobSeekerID']);
                dump("Profile Exists?", \App\Domain\User\Models\JobSeekerProfile::where('JobSeekerID', $data[0]['JobSeekerID'])->exists());
                // Direct relation check
                $app = \App\Domain\Application\Models\JobApplication::find($data[0]['ApplicationID']);
                dump("Relation jobSeeker:", $app->jobSeeker);
            }
        }

        $response->assertStatus(200)
            ->assertJsonFragment(['JobSeekerID' => $seeker->UserID]);
    }

    public function test_employer_can_update_application_status()
    {
        // Setup
        $employer = User::factory()->create();
        $employer->roles()->attach(Role::where('RoleName', 'Employer')->first());
        DB::table('companyprofile')->insert(['CompanyID' => $employer->UserID]);
        $jobId = DB::table('jobad')->insertGetId([
            'CompanyID' => $employer->UserID,
            'Title' => 'Job 1',
            'Status' => 'Active'
        ]);

        $seeker = User::factory()->create();
        DB::table('jobseekerprofile')->insert(['JobSeekerID' => $seeker->UserID]);
        $cvId = DB::table('cv')->insertGetId(['JobSeekerID' => $seeker->UserID, 'Title' => 'CV']);

        $appId = DB::table('jobapplication')->insertGetId([
            'JobAdID' => $jobId,
            'JobSeekerID' => $seeker->UserID,
            'CVID' => $cvId,
            'AppliedAt' => now(),
            'Status' => 'Pending',
        ]);

        $response = $this->actingAs($employer)->putJson("/api/employer/applications/{$appId}/status", [
            'status' => 'Shortlisted',
            'notes' => 'Good profile',
        ]);

        if ($response->status() !== 200) {
            dump($response->json());
        }

        $response->assertStatus(200)
            ->assertJson(['message' => 'Application status updated']);

        $this->assertDatabaseHas('jobapplication', [
            'ApplicationID' => $appId,
            'Status' => 'Shortlisted',
            'Notes' => 'Good profile',
        ]);
    }
}
