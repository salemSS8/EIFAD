<?php

namespace Tests\Feature\Api\Admin;

use App\Domain\Application\Models\JobApplication;
use App\Domain\Communication\Models\Notification;
use App\Domain\Company\Models\CompanyProfile;
use App\Domain\CV\Models\CV;
use App\Domain\CV\Models\CVCertification;
use App\Domain\Job\Models\JobAd;
use App\Domain\User\Models\Role;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['RoleName' => 'Admin']);
        Role::create(['RoleName' => 'Employer']);
        Role::create(['RoleName' => 'JobSeeker']);

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('RoleName', 'Admin')->first());
    }

    public function test_statistics_returns_correct_structure(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/users/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [
                'total_job_seekers' => ['count', 'growth_percentage'],
                'total_companies' => ['count', 'growth_percentage'],
                'active_job_ads' => ['count', 'growth_percentage'],
                'total_applications' => ['count', 'growth_percentage'],
                'pending_company_verifications',
                'trusted_job_seekers',
                'pending_certificate_reviews',
                'ai_alerts_count',
            ]]);
    }

    public function test_statistics_counts_job_seekers_correctly(): void
    {
        // Create 3 job seekers
        $jobSeekerRole = Role::where('RoleName', 'JobSeeker')->first();
        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->create();
            $user->roles()->attach($jobSeekerRole);
            DB::table('jobseekerprofile')->insert(['JobSeekerID' => $user->UserID]);
        }

        // Create 1 employer (should NOT be counted)
        $employer = User::factory()->create();
        $employer->roles()->attach(Role::where('RoleName', 'Employer')->first());

        $response = $this->actingAs($this->admin)->getJson('/api/admin/users/statistics');

        $response->assertStatus(200)
            ->assertJsonPath('data.total_job_seekers.count', 3);
    }

    public function test_statistics_counts_companies_correctly(): void
    {
        // Create 2 employers with company profiles
        $employerRole = Role::where('RoleName', 'Employer')->first();
        for ($i = 0; $i < 2; $i++) {
            $user = User::factory()->create();
            $user->roles()->attach($employerRole);
            CompanyProfile::create(['CompanyID' => $user->UserID]);
        }

        $response = $this->actingAs($this->admin)->getJson('/api/admin/users/statistics');

        $response->assertStatus(200)
            ->assertJsonPath('data.total_companies.count', 2);
    }

    public function test_statistics_counts_active_job_ads(): void
    {
        $employer = User::factory()->create();
        $employer->roles()->attach(Role::where('RoleName', 'Employer')->first());
        CompanyProfile::create(['CompanyID' => $employer->UserID]);

        // 2 active, 1 closed
        JobAd::create([
            'CompanyID' => $employer->UserID,
            'Title' => 'Active Job 1',
            'Status' => 'Active',
            'PostedAt' => now(),
        ]);
        JobAd::create([
            'CompanyID' => $employer->UserID,
            'Title' => 'Active Job 2',
            'Status' => 'Active',
            'PostedAt' => now(),
        ]);
        JobAd::create([
            'CompanyID' => $employer->UserID,
            'Title' => 'Closed Job',
            'Status' => 'Closed',
            'PostedAt' => now(),
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/admin/users/statistics');

        $response->assertStatus(200)
            ->assertJsonPath('data.active_job_ads.count', 2);
    }

    public function test_statistics_counts_applications(): void
    {
        $employer = User::factory()->create();
        $employer->roles()->attach(Role::where('RoleName', 'Employer')->first());
        CompanyProfile::create(['CompanyID' => $employer->UserID]);

        $jobAd = JobAd::create([
            'CompanyID' => $employer->UserID,
            'Title' => 'Test Job',
            'Status' => 'Active',
            'PostedAt' => now(),
        ]);

        $jobSeekerRole = Role::where('RoleName', 'JobSeeker')->first();
        for ($i = 0; $i < 4; $i++) {
            $seeker = User::factory()->create();
            $seeker->roles()->attach($jobSeekerRole);
            DB::table('jobseekerprofile')->insert(['JobSeekerID' => $seeker->UserID]);
            JobApplication::create([
                'JobAdID' => $jobAd->JobAdID,
                'JobSeekerID' => $seeker->UserID,
                'Status' => 'pending',
                'AppliedAt' => now(),
            ]);
        }

        $response = $this->actingAs($this->admin)->getJson('/api/admin/users/statistics');

        $response->assertStatus(200)
            ->assertJsonPath('data.total_applications.count', 4);
    }

    public function test_statistics_counts_pending_company_verifications(): void
    {
        $employerRole = Role::where('RoleName', 'Employer')->first();

        // 2 pending
        for ($i = 0; $i < 2; $i++) {
            $user = User::factory()->create();
            $user->roles()->attach($employerRole);
            CompanyProfile::create(['CompanyID' => $user->UserID, 'VerificationStatus' => 'Pending']);
        }

        // 1 verified (should NOT count)
        $verified = User::factory()->create();
        $verified->roles()->attach($employerRole);
        CompanyProfile::create(['CompanyID' => $verified->UserID, 'VerificationStatus' => 'Verified']);

        $response = $this->actingAs($this->admin)->getJson('/api/admin/users/statistics');

        $response->assertStatus(200)
            ->assertJsonPath('data.pending_company_verifications', 2);
    }

    public function test_statistics_counts_trusted_job_seekers(): void
    {
        $jobSeekerRole = Role::where('RoleName', 'JobSeeker')->first();

        // 3 trusted
        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->create();
            $user->roles()->attach($jobSeekerRole);
            DB::table('jobseekerprofile')->insert([
                'JobSeekerID' => $user->UserID,
                'Status' => 'trusted',
            ]);
        }

        // 2 nottrusted (should NOT count)
        for ($i = 0; $i < 2; $i++) {
            $user = User::factory()->create();
            $user->roles()->attach($jobSeekerRole);
            DB::table('jobseekerprofile')->insert([
                'JobSeekerID' => $user->UserID,
                'Status' => 'notrusted',
            ]);
        }

        $response = $this->actingAs($this->admin)->getJson('/api/admin/users/statistics');

        $response->assertStatus(200)
            ->assertJsonPath('data.trusted_job_seekers', 3);
    }

    public function test_statistics_counts_pending_certificate_reviews(): void
    {
        $seeker = User::factory()->create();
        $seeker->roles()->attach(Role::where('RoleName', 'JobSeeker')->first());
        DB::table('jobseekerprofile')->insert(['JobSeekerID' => $seeker->UserID]);

        $cv = CV::create([
            'JobSeekerID' => $seeker->UserID,
            'Title' => 'Test CV',
            'CreatedAt' => now(),
        ]);

        // 2 pending + 1 ai_reviewed = 3 should count
        for ($i = 0; $i < 2; $i++) {
            CVCertification::create([
                'CVID' => $cv->CVID,
                'CertificateName' => "Pending Cert {$i}",
                'IsVerified' => false,
                'VerificationStatus' => 'pending',
            ]);
        }
        CVCertification::create([
            'CVID' => $cv->CVID,
            'CertificateName' => 'AI Reviewed Cert',
            'IsVerified' => false,
            'VerificationStatus' => 'ai_reviewed',
        ]);

        // 1 verified (should NOT count)
        CVCertification::create([
            'CVID' => $cv->CVID,
            'CertificateName' => 'Verified Cert',
            'IsVerified' => true,
            'VerificationStatus' => 'verified',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/admin/users/statistics');

        $response->assertStatus(200)
            ->assertJsonPath('data.pending_certificate_reviews', 3);
    }

    public function test_statistics_counts_ai_alerts(): void
    {
        // Create AI alert notifications for admin
        for ($i = 0; $i < 5; $i++) {
            Notification::create([
                'UserID' => $this->admin->UserID,
                'Type' => 'ai_alert',
                'Content' => "AI Alert {$i}",
                'IsRead' => false,
                'CreatedAt' => now(),
            ]);
        }

        // 2 read alerts (should NOT count)
        for ($i = 0; $i < 2; $i++) {
            Notification::create([
                'UserID' => $this->admin->UserID,
                'Type' => 'ai_alert',
                'Content' => "Read Alert {$i}",
                'IsRead' => true,
                'CreatedAt' => now(),
            ]);
        }

        // 1 non-AI notification (should NOT count)
        Notification::create([
            'UserID' => $this->admin->UserID,
            'Type' => 'message',
            'Content' => 'Regular notification',
            'IsRead' => false,
            'CreatedAt' => now(),
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/admin/users/statistics');

        $response->assertStatus(200)
            ->assertJsonPath('data.ai_alerts_count', 5);
    }

    public function test_statistics_growth_percentage_with_data(): void
    {
        $jobSeekerRole = Role::where('RoleName', 'JobSeeker')->first();

        // Create 2 job seekers last month
        for ($i = 0; $i < 2; $i++) {
            $user = User::factory()->create(['CreatedAt' => now()->subMonth()->startOfMonth()->addDays($i)]);
            $user->roles()->attach($jobSeekerRole);
            DB::table('jobseekerprofile')->insert(['JobSeekerID' => $user->UserID]);
        }

        // Create 3 job seekers this month
        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->create(['CreatedAt' => now()->startOfMonth()->addDays($i)]);
            $user->roles()->attach($jobSeekerRole);
            DB::table('jobseekerprofile')->insert(['JobSeekerID' => $user->UserID]);
        }

        $response = $this->actingAs($this->admin)->getJson('/api/admin/users/statistics');

        $response->assertStatus(200);
        $data = $response->json('data');

        // Growth: (3 - 2) / 2 * 100 = 50%
        $this->assertEquals(50.0, $data['total_job_seekers']['growth_percentage']);
    }

    public function test_statistics_growth_percentage_zero_last_month(): void
    {
        $jobSeekerRole = Role::where('RoleName', 'JobSeeker')->first();

        // Create 2 job seekers this month (none last month)
        for ($i = 0; $i < 2; $i++) {
            $user = User::factory()->create(['CreatedAt' => now()->startOfMonth()->addDays($i)]);
            $user->roles()->attach($jobSeekerRole);
            DB::table('jobseekerprofile')->insert(['JobSeekerID' => $user->UserID]);
        }

        $response = $this->actingAs($this->admin)->getJson('/api/admin/users/statistics');

        $response->assertStatus(200);
        $data = $response->json('data');

        // Growth: 100% (no baseline)
        $this->assertEquals(100.0, $data['total_job_seekers']['growth_percentage']);
    }

    public function test_statistics_growth_percentage_no_data_both_months(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/users/statistics');

        $response->assertStatus(200);
        $data = $response->json('data');

        // Growth: 0% (no data either month)
        $this->assertEquals(0.0, $data['total_job_seekers']['growth_percentage']);
    }

    public function test_non_admin_cannot_access_statistics(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/admin/users/statistics');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_statistics(): void
    {
        $response = $this->getJson('/api/admin/users/statistics');

        $response->assertStatus(401);
    }
}
