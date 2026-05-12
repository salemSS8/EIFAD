<?php

namespace Tests\Feature\Api\Admin;

use App\Domain\Company\Models\CompanyProfile;
use App\Domain\Job\Models\JobAd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminJobControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $companyUser;
    private CompanyProfile $companyProfile;
    private JobAd $jobAd;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create an Admin user
        $this->admin = User::factory()->create([
            'Email' => 'admin_test_jobs@example.com',
        ]);
        $adminRole = \App\Models\Role::firstOrCreate(['RoleName' => 'Admin']);
        \App\Models\UserRole::create([
            'UserID' => $this->admin->UserID,
            'RoleID' => $adminRole->RoleID,
            'AssignedAt' => now(),
        ]);

        // 2. Create a Company user & profile
        $this->companyUser = User::factory()->create([
            'Email' => 'company_test_jobs@example.com',
        ]);
        $companyRole = \App\Models\Role::firstOrCreate(['RoleName' => 'Employer']);
        \App\Models\UserRole::create([
            'UserID' => $this->companyUser->UserID,
            'RoleID' => $companyRole->RoleID,
            'AssignedAt' => now(),
        ]);

        $this->companyProfile = CompanyProfile::create([
            'CompanyID' => $this->companyUser->UserID,
            'CompanyName' => 'Test Tech Company',
            'IsCompanyVerified' => true,
        ]);

        // 3. Create a JobAd
        $this->jobAd = JobAd::create([
            'CompanyID' => $this->companyProfile->CompanyID,
            'Title' => 'Backend Developer',
            'Description' => 'Test Description',
            'Status' => 'Active',
            'PostedAt' => now(),
        ]);
    }

    public function test_admin_can_list_all_jobs()
    {
        Sanctum::actingAs($this->admin, ['*']);

        $response = $this->getJson('/api/admin/jobs');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.Title', 'Backend Developer');
    }

    public function test_admin_can_filter_jobs_by_status()
    {
        Sanctum::actingAs($this->admin, ['*']);

        JobAd::create([
            'CompanyID' => $this->companyProfile->CompanyID,
            'Title' => 'Deleted Job',
            'Description' => 'Test Description',
            'Status' => 'Deleted',
        ])->delete(); // Soft delete it too for realism

        $response = $this->getJson('/api/admin/jobs?status=Deleted');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.Title', 'Deleted Job');
    }

    public function test_admin_can_view_job_details()
    {
        Sanctum::actingAs($this->admin, ['*']);

        $response = $this->getJson('/api/admin/jobs/' . $this->jobAd->JobAdID);

        $response->assertStatus(200)
            ->assertJsonPath('data.Title', 'Backend Developer')
            ->assertJsonPath('data.company.CompanyName', 'Test Tech Company');
    }

    public function test_admin_can_update_job_status_and_restore()
    {
        Sanctum::actingAs($this->admin, ['*']);

        // First, soft delete the job and set status to Deleted
        $this->jobAd->update(['Status' => 'Deleted']);
        $this->jobAd->delete();

        // Ensure it's deleted
        $this->assertTrue($this->jobAd->trashed());

        // Update to Active
        $response = $this->putJson('/api/admin/jobs/' . $this->jobAd->JobAdID, [
            'status' => 'Active',
            'title' => 'Restored Developer',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.Title', 'Restored Developer')
            ->assertJsonPath('data.Status', 'Active');

        $this->assertFalse($this->jobAd->fresh()->trashed());
    }

    public function test_admin_can_delete_job()
    {
        Sanctum::actingAs($this->admin, ['*']);

        $response = $this->deleteJson('/api/admin/jobs/' . $this->jobAd->JobAdID);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Job deleted successfully');

        $this->jobAd->refresh();
        $this->assertEquals('Deleted', $this->jobAd->Status);
        $this->assertTrue($this->jobAd->trashed());
    }

    public function test_non_admin_cannot_access_job_management()
    {
        Sanctum::actingAs($this->companyUser, ['*']);

        $response = $this->getJson('/api/admin/jobs');
        $response->assertStatus(403);

        $response = $this->deleteJson('/api/admin/jobs/' . $this->jobAd->JobAdID);
        $response->assertStatus(403);
    }
}
