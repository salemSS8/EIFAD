<?php

namespace Tests\Feature\Api\Profile;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domain\User\Models\User;
use App\Domain\User\Models\Role;
use Illuminate\Support\Facades\DB;

class CompanyProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['RoleName' => 'Employer']);
    }

    public function test_employer_can_view_profile()
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('RoleName', 'Employer')->first());

        DB::table('companyprofile')->insert([
            'CompanyID' => $user->UserID,
            'CompanyName' => 'Tech Corp',
            'OrganizationName' => 'Tech Corp Inc.',
            'Address' => 'Sana\'a',
        ]);

        $response = $this->actingAs($user)->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJson([
                'type' => 'company',
                'data' => [
                    'CompanyName' => 'Tech Corp',
                    'Address' => 'Sana\'a',
                ]
            ]);
    }

    public function test_employer_can_update_profile()
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('RoleName', 'Employer')->first());

        $response = $this->actingAs($user)->putJson('/api/profile', [
            'company_name' => 'New Tech',
            'website_url' => 'https://example.com',
            'established_year' => 2020,
            'employee_count' => 50,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Company profile updated successfully',
                'data' => [
                    'CompanyName' => 'New Tech',
                    'WebsiteURL' => 'https://example.com',
                ]
            ]);

        $this->assertDatabaseHas('companyprofile', [
            'CompanyID' => $user->UserID,
            'CompanyName' => 'New Tech',
        ]);
    }

    public function test_company_profile_validation()
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('RoleName', 'Employer')->first());

        $response = $this->actingAs($user)->putJson('/api/profile', [
            // company_name is required
            'website_url' => 'not-a-url',
            'established_year' => 1700, // min 1800
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['company_name', 'website_url', 'established_year']);
    }

    public function test_employer_can_delete_profile_with_no_active_jobs()
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('RoleName', 'Employer')->first());
        DB::table('companyprofile')->insert(['CompanyID' => $user->UserID]);

        $response = $this->actingAs($user)->deleteJson('/api/profile');

        if ($response->status() !== 200) {
            dump($response->json());
        }

        $response->assertStatus(200)
            ->assertJson(['message' => 'تم حذف ملف الشركة بنجاح']);

        $this->assertDatabaseMissing('companyprofile', ['CompanyID' => $user->UserID]);
    }

    public function test_employer_cannot_delete_profile_with_active_jobs()
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('RoleName', 'Employer')->first());
        DB::table('companyprofile')->insert(['CompanyID' => $user->UserID]);

        // Create Active Job
        DB::table('jobad')->insert([
            'CompanyID' => $user->UserID,
            'Title' => 'Dev',
            'Status' => 'Active',
            'PostedAt' => now(),
        ]);

        $response = $this->actingAs($user)->deleteJson('/api/profile');

        $response->assertStatus(422)
            ->assertJson(['message' => 'لا يمكن حذف ملف الشركة وهناك إعلانات وظائف نشطة، يرجى إغلاقها أولاً']);

        $this->assertDatabaseHas('companyprofile', ['CompanyID' => $user->UserID]);
    }
}
