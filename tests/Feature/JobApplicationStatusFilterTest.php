<?php

namespace Tests\Feature;

use App\Domain\User\Models\Role;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JobApplicationStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $employer;

    private int $jobId;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['RoleName' => 'Employer']);
        Role::create(['RoleName' => 'JobSeeker']);

        // Create employer with company profile and a job
        $this->employer = User::factory()->create();
        $this->employer->roles()->attach(Role::where('RoleName', 'Employer')->first());

        DB::table('companyprofile')->insert([
            'CompanyID' => $this->employer->UserID,
            'CompanyName' => 'شركة اختبار',
        ]);

        $this->jobId = DB::table('jobad')->insertGetId([
            'CompanyID' => $this->employer->UserID,
            'Title' => 'وظيفة اختبار',
            'Description' => 'وصف',
            'Status' => 'Active',
            'PostedAt' => now(),
        ]);

        // Create job seekers and applications with different statuses
        $statuses = ['Pending', 'Reviewed', 'Hired', 'Rejected', 'Hired'];
        foreach ($statuses as $i => $status) {
            $seeker = User::factory()->create();
            $seeker->roles()->attach(Role::where('RoleName', 'JobSeeker')->first());

            DB::table('jobseekerprofile')->insert([
                'JobSeekerID' => $seeker->UserID,
            ]);

            DB::table('jobapplication')->insert([
                'JobAdID' => $this->jobId,
                'JobSeekerID' => $seeker->UserID,
                'AppliedAt' => now(),
                'Status' => $status,
                'MatchScore' => rand(50, 95),
            ]);
        }
    }

    public function test_employer_can_get_all_applications_without_filter(): void
    {
        $response = $this->actingAs($this->employer)
            ->getJson("/api/employer/jobs/{$this->jobId}/applications");

        $response->assertStatus(200);
        $this->assertEquals(5, $response->json('total'));
    }

    public function test_employer_can_filter_applications_by_hired_status(): void
    {
        $response = $this->actingAs($this->employer)
            ->getJson("/api/employer/jobs/{$this->jobId}/applications?status=Hired");

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('total'));

        $statuses = collect($response->json('data'))->pluck('Status')->unique()->values();
        $this->assertEquals(['Hired'], $statuses->toArray());
    }

    public function test_employer_can_filter_applications_by_rejected_status(): void
    {
        $response = $this->actingAs($this->employer)
            ->getJson("/api/employer/jobs/{$this->jobId}/applications?status=Rejected");

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('total'));
    }

    public function test_employer_can_filter_applications_by_pending_status(): void
    {
        $response = $this->actingAs($this->employer)
            ->getJson("/api/employer/jobs/{$this->jobId}/applications?status=Pending");

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('total'));
    }

    public function test_empty_result_for_nonexistent_status(): void
    {
        $response = $this->actingAs($this->employer)
            ->getJson("/api/employer/jobs/{$this->jobId}/applications?status=Offered");

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('total'));
    }
}
