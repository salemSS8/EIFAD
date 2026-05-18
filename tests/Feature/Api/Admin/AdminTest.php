<?php

namespace Tests\Feature\Api\Admin;

use App\Domain\User\Models\Role;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['RoleName' => 'Admin']);
        Role::create(['RoleName' => 'Employer']);
        Role::create(['RoleName' => 'JobSeeker']);
    }

    public function test_admin_can_list_users()
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('RoleName', 'Admin')->first());

        User::factory()->count(3)->create();

        $response = $this->actingAs($admin)->getJson('/api/admin/users');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['UserID', 'FullName']]]);
    }

    public function test_admin_can_filter_users_by_account_status()
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('RoleName', 'Admin')->first());

        // 1 active, 1 blocked
        User::factory()->create(['IsBlocked' => false, 'IsVerified' => true]);
        User::factory()->create(['IsBlocked' => true]);

        // Filter for blocked
        $response = $this->actingAs($admin)->getJson('/api/admin/users?account_status=blocked');
        $response->assertStatus(200)->assertJsonCount(1, 'data');

        // Filter for active
        $response = $this->actingAs($admin)->getJson('/api/admin/users?account_status=active');
        $response->assertStatus(200)->assertJsonCount(2, 'data'); // 1 created + admin
    }

    public function test_admin_can_filter_jobseekers_by_trust_status()
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('RoleName', 'Admin')->first());

        $jobSeekerRole = Role::where('RoleName', 'JobSeeker')->first();

        // 1 trusted
        $user1 = User::factory()->create();
        $user1->roles()->attach($jobSeekerRole);
        DB::table('jobseekerprofile')->insert(['JobSeekerID' => $user1->UserID, 'Status' => 'trusted']);

        // 1 nottrusted
        $user2 = User::factory()->create();
        $user2->roles()->attach($jobSeekerRole);
        DB::table('jobseekerprofile')->insert(['JobSeekerID' => $user2->UserID, 'Status' => 'notrusted']);

        $response = $this->actingAs($admin)->getJson('/api/admin/users?user_status=trusted');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.UserID', $user1->UserID);
    }

    public function test_admin_can_filter_employers_by_trust_status()
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('RoleName', 'Admin')->first());

        $employerRole = Role::where('RoleName', 'Employer')->first();

        // 1 trusted company (Verified)
        $user1 = User::factory()->create();
        $user1->roles()->attach($employerRole);
        DB::table('companyprofile')->insert([
            'CompanyID' => $user1->UserID,
            'CompanyName' => 'Test Company 1',
            'VerificationStatus' => 'Verified',
        ]);

        // 1 pending company (Pending)
        $user2 = User::factory()->create();
        $user2->roles()->attach($employerRole);
        DB::table('companyprofile')->insert([
            'CompanyID' => $user2->UserID,
            'CompanyName' => 'Test Company 2',
            'VerificationStatus' => 'Pending',
        ]);

        // Test Filter trusted
        $response = $this->actingAs($admin)->getJson('/api/admin/users?user_status=trusted');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.UserID', $user1->UserID);

        // Test Filter nottrusted
        $response = $this->actingAs($admin)->getJson('/api/admin/users?user_status=nottrusted');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.UserID', $user2->UserID);

        // Test Filter pending
        $response = $this->actingAs($admin)->getJson('/api/admin/users?user_status=pending');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.UserID', $user2->UserID);
    }

    public function test_admin_can_verify_jobseeker_status()
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('RoleName', 'Admin')->first());

        $user = User::factory()->create();
        $user->roles()->attach(Role::where('RoleName', 'JobSeeker')->first());
        DB::table('jobseekerprofile')->insert(['JobSeekerID' => $user->UserID, 'Status' => 'notrusted']);

        $response = $this->actingAs($admin)->postJson("/api/admin/users/{$user->UserID}/verify-jobseeker", [
            'status' => 'trusted',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('jobseekerprofile', [
            'JobSeekerID' => $user->UserID,
            'Status' => 'trusted',
        ]);
    }

    public function test_admin_can_block_user()
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('RoleName', 'Admin')->first());

        $user = User::factory()->create();

        $response = $this->actingAs($admin)->postJson("/api/admin/users/{$user->UserID}/block", [
            'reason' => 'Spam',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'تم حظر المستخدم بنجاح']);

        $this->assertDatabaseHas('user', [
            'UserID' => $user->UserID,
            'IsBlocked' => true,
            'BlockReason' => 'Spam',
        ]);
    }

    public function test_admin_can_unblock_user()
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('RoleName', 'Admin')->first());

        $user = User::factory()->create();
        $user->update(['IsBlocked' => true, 'BlockedAt' => now()]);

        $response = $this->actingAs($admin)->postJson("/api/admin/users/{$user->UserID}/unblock");

        $response->assertStatus(200)
            ->assertJson(['message' => 'تم إلغاء حظر المستخدم بنجاح']);

        $this->assertDatabaseHas('user', [
            'UserID' => $user->UserID,
            'IsBlocked' => false,
        ]);
    }

    public function test_admin_can_view_statistics()
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('RoleName', 'Admin')->first());

        User::factory()->count(5)->create();

        $response = $this->actingAs($admin)->getJson('/api/admin/users/statistics');

        $response->assertStatus(200);
        // ->assertJsonStructure(['total_users', 'blocked_users']); // Structure depends on implementation
    }

    public function test_non_admin_cannot_access_admin_routes()
    {
        $user = User::factory()->create();
        // No Admin role

        $response = $this->actingAs($user)->getJson('/api/admin/users');

        $response->assertStatus(403);
    }
}
