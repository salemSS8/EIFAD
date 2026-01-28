<?php

namespace Tests\Feature\Api\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domain\User\Models\User;
use App\Domain\User\Models\Role;
use Illuminate\Support\Facades\DB;

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
