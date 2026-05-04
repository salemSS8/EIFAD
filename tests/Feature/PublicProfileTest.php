<?php

namespace Tests\Feature;

use App\Domain\User\Models\Role;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['RoleName' => 'JobSeeker']);
        Role::create(['RoleName' => 'Employer']);
    }

    public function test_can_view_job_seeker_public_profile(): void
    {
        $user = User::factory()->create(['FullName' => 'أحمد المطور']);
        $user->roles()->attach(Role::where('RoleName', 'JobSeeker')->first());

        DB::table('jobseekerprofile')->insert([
            'JobSeekerID' => $user->UserID,
            'Location' => 'صنعاء',
            'ProfileSummary' => 'مطور ويب',
        ]);

        $viewer = User::factory()->create();
        $viewer->roles()->attach(Role::where('RoleName', 'Employer')->first());

        $response = $this->actingAs($viewer)->getJson("/api/users/{$user->UserID}/profile");

        $response->assertStatus(200)
            ->assertJson([
                'type' => 'job_seeker',
                'data' => [
                    'FullName' => 'أحمد المطور',
                    'Location' => 'صنعاء',
                    'ProfileSummary' => 'مطور ويب',
                ],
            ]);
    }

    public function test_can_view_employer_public_profile(): void
    {
        $employer = User::factory()->create(['FullName' => 'مدير الشركة']);
        $employer->roles()->attach(Role::where('RoleName', 'Employer')->first());

        DB::table('companyprofile')->insert([
            'CompanyID' => $employer->UserID,
            'CompanyName' => 'شركة التقنية',
            'FieldOfWork' => 'تكنولوجيا المعلومات',
            'Description' => 'شركة تقنية رائدة',
        ]);

        $viewer = User::factory()->create();
        $viewer->roles()->attach(Role::where('RoleName', 'JobSeeker')->first());

        $response = $this->actingAs($viewer)->getJson("/api/users/{$employer->UserID}/profile");

        $response->assertStatus(200)
            ->assertJson([
                'type' => 'company',
                'data' => [
                    'CompanyName' => 'شركة التقنية',
                    'FieldOfWork' => 'تكنولوجيا المعلومات',
                ],
            ]);
    }

    public function test_returns_404_for_nonexistent_user(): void
    {
        $viewer = User::factory()->create();
        $viewer->roles()->attach(Role::where('RoleName', 'JobSeeker')->first());

        $response = $this->actingAs($viewer)->getJson('/api/users/99999/profile');

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_access_public_profile(): void
    {
        $response = $this->getJson('/api/users/1/profile');

        $response->assertStatus(401);
    }

    public function test_job_seeker_profile_includes_cv_data(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('RoleName', 'JobSeeker')->first());

        DB::table('jobseekerprofile')->insert([
            'JobSeekerID' => $user->UserID,
            'Location' => 'عدن',
        ]);

        DB::table('cv')->insert([
            'JobSeekerID' => $user->UserID,
            'Title' => 'سيرتي الذاتية',
            'PersonalSummary' => 'ملخص شخصي',
            'CreatedAt' => now(),
        ]);

        $viewer = User::factory()->create();
        $viewer->roles()->attach(Role::where('RoleName', 'Employer')->first());

        $response = $this->actingAs($viewer)->getJson("/api/users/{$user->UserID}/profile");

        $response->assertStatus(200)
            ->assertJsonPath('data.cv.Title', 'سيرتي الذاتية');
    }
}
