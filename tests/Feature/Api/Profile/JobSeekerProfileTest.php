<?php

namespace Tests\Feature\Api\Profile;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domain\User\Models\User;
use App\Domain\User\Models\Role;
use Illuminate\Support\Facades\DB;

class JobSeekerProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['RoleName' => 'JobSeeker']);
    }

    public function test_job_seeker_can_view_profile()
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('RoleName', 'JobSeeker')->first());

        // Use DB directly if factory relationship setup is tricky in test
        DB::table('jobseekerprofile')->insert([
            'JobSeekerID' => $user->UserID,
            'Location' => 'Yemen',
            'ProfileSummary' => 'Software Engineer',
        ]);

        $response = $this->actingAs($user)->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJson([
                'type' => 'job_seeker',
                'data' => [
                    'Location' => 'Yemen',
                    'ProfileSummary' => 'Software Engineer',
                ]
            ]);
    }

    public function test_job_seeker_can_update_profile()
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('RoleName', 'JobSeeker')->first());
        // Profile might not exist initally if not registered via API, but controller uses updateOrCreate

        $response = $this->actingAs($user)->putJson('/api/profile', [
            'location' => 'Aden',
            'profile_summary' => 'Updated Summary',
            'personal_photo' => 'profile.jpg',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Profile updated successfully',
                'data' => [
                    'Location' => 'Aden',
                    'ProfileSummary' => 'Updated Summary',
                ]
            ]);

        $this->assertDatabaseHas('jobseekerprofile', [
            'JobSeekerID' => $user->UserID,
            'Location' => 'Aden',
        ]);
    }

    public function test_job_seeker_profile_validation()
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('RoleName', 'JobSeeker')->first());

        $response = $this->actingAs($user)->putJson('/api/profile', [
            'personal_photo' => str_repeat('a', 256), // Too long
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['personal_photo']);
    }

    public function test_job_seeker_can_delete_profile()
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('RoleName', 'JobSeeker')->first());
        DB::table('jobseekerprofile')->insert(['JobSeekerID' => $user->UserID]);

        $response = $this->actingAs($user)->deleteJson('/api/profile');

        $response->assertStatus(200)
            ->assertJson(['message' => 'تم حذف الملف الشخصي بنجاح']);

        $this->assertDatabaseMissing('jobseekerprofile', ['JobSeekerID' => $user->UserID]);
    }
}
