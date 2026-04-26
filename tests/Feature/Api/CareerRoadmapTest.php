<?php

namespace Tests\Feature\Api;

use App\Domain\Course\Models\Roadmap;
use App\Domain\CV\Models\CV;
use App\Domain\Shared\Services\GeminiAIService;
use App\Domain\User\Models\JobSeekerProfile;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CareerRoadmapTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Mock AI response data used across tests.
     *
     * @return array<string, mixed>
     */
    private function fakeAiResponse(): array
    {
        return [
            'current_level' => 'Junior Developer',
            'target_level' => 'Senior Backend Developer',
            'milestones' => [
                [
                    'title' => 'Phase 1: Foundations',
                    'duration' => '3 months',
                    'skills_to_learn' => ['Design Patterns', 'Testing'],
                    'actions' => ['Read Clean Code book', 'Write unit tests'],
                ],
            ],
            'total_estimated_time' => '12 months',
            '_meta' => [
                'model' => 'gemini-1.5-flash',
                'prompt_version' => '2.0.0',
                'input_hash' => 'abc123',
            ],
        ];
    }

    /**
     * Create a job seeker user with profile and CV.
     *
     * @return array{user: User, cv: CV}
     */
    private function createJobSeekerWithCv(): array
    {
        $user = User::factory()->create();
        JobSeekerProfile::create([
            'JobSeekerID' => $user->UserID,
            'Location' => 'Test City',
        ]);
        $cv = CV::create([
            'JobSeekerID' => $user->UserID,
            'Title' => 'Test Developer',
            'PersonalSummary' => 'A test developer.',
            'CreatedAt' => now(),
            'UpdatedAt' => now(),
        ]);

        return ['user' => $user, 'cv' => $cv];
    }

    public function test_unauthenticated_user_cannot_generate_roadmap(): void
    {
        $response = $this->postJson('/api/career-roadmap', [
            'target_role' => 'Senior Developer',
            'cv_id' => 1,
        ]);

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_view_roadmap(): void
    {
        $response = $this->getJson('/api/career-roadmap');

        $response->assertStatus(401);
    }

    public function test_validation_fails_without_target_role(): void
    {
        ['user' => $user, 'cv' => $cv] = $this->createJobSeekerWithCv();

        $response = $this->actingAs($user)->postJson('/api/career-roadmap', [
            'cv_id' => $cv->CVID,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['target_role']);
    }

    public function test_validation_fails_without_cv_id(): void
    {
        ['user' => $user] = $this->createJobSeekerWithCv();

        $response = $this->actingAs($user)->postJson('/api/career-roadmap', [
            'target_role' => 'Senior Developer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cv_id']);
    }

    public function test_validation_fails_with_nonexistent_cv(): void
    {
        ['user' => $user] = $this->createJobSeekerWithCv();

        $response = $this->actingAs($user)->postJson('/api/career-roadmap', [
            'target_role' => 'Senior Developer',
            'cv_id' => 9999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cv_id']);
    }

    public function test_user_without_jobseeker_profile_gets_403(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        JobSeekerProfile::create(['JobSeekerID' => $otherUser->UserID]);
        $cv = CV::create([
            'JobSeekerID' => $otherUser->UserID,
            'Title' => 'Test',
            'CreatedAt' => now(),
            'UpdatedAt' => now(),
        ]);

        $response = $this->actingAs($user)->postJson('/api/career-roadmap', [
            'target_role' => 'Senior Developer',
            'cv_id' => $cv->CVID,
        ]);

        $response->assertStatus(403);
    }

    public function test_generate_roadmap_succeeds(): void
    {
        ['user' => $user, 'cv' => $cv] = $this->createJobSeekerWithCv();

        $mock = Mockery::mock(GeminiAIService::class);
        $mock->shouldReceive('generateCareerRoadmap')
            ->once()
            ->andReturn($this->fakeAiResponse());
        $this->app->instance(GeminiAIService::class, $mock);

        $response = $this->actingAs($user)->postJson('/api/career-roadmap', [
            'target_role' => 'Senior Backend Developer',
            'cv_id' => $cv->CVID,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.target_role', 'Senior Backend Developer')
            ->assertJsonPath('data.current_level', 'Junior Developer')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('roadmaps', [
            'user_id' => $user->UserID,
            'target_role' => 'Senior Backend Developer',
            'is_active' => true,
        ]);
    }

    public function test_generating_new_roadmap_deactivates_old_one(): void
    {
        ['user' => $user, 'cv' => $cv] = $this->createJobSeekerWithCv();

        // Create an existing active roadmap
        $oldRoadmap = Roadmap::create([
            'user_id' => $user->UserID,
            'title' => 'Old Roadmap',
            'target_role' => 'Junior Developer',
            'generated_at' => now()->subDay(),
            'is_active' => true,
        ]);

        $mock = Mockery::mock(GeminiAIService::class);
        $mock->shouldReceive('generateCareerRoadmap')
            ->once()
            ->andReturn($this->fakeAiResponse());
        $this->app->instance(GeminiAIService::class, $mock);

        $response = $this->actingAs($user)->postJson('/api/career-roadmap', [
            'target_role' => 'Senior Backend Developer',
            'cv_id' => $cv->CVID,
        ]);

        $response->assertStatus(200);

        $oldRoadmap->refresh();
        $this->assertFalse($oldRoadmap->is_active);

        $this->assertDatabaseHas('roadmaps', [
            'user_id' => $user->UserID,
            'target_role' => 'Senior Backend Developer',
            'is_active' => true,
        ]);
    }

    public function test_show_roadmap_returns_active_roadmap(): void
    {
        ['user' => $user] = $this->createJobSeekerWithCv();

        Roadmap::create([
            'user_id' => $user->UserID,
            'title' => 'My Roadmap',
            'target_role' => 'Senior Developer',
            'current_level' => 'Junior',
            'target_level' => 'Senior',
            'milestones' => [['title' => 'Phase 1']],
            'total_estimated_time' => '6 months',
            'generated_at' => now(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->getJson('/api/career-roadmap');

        $response->assertStatus(200)
            ->assertJsonPath('data.target_role', 'Senior Developer')
            ->assertJsonPath('data.is_active', true);
    }

    public function test_show_roadmap_returns_404_when_none_exists(): void
    {
        ['user' => $user] = $this->createJobSeekerWithCv();

        $response = $this->actingAs($user)->getJson('/api/career-roadmap');

        $response->assertStatus(404)
            ->assertJsonPath('data', null);
    }

    public function test_show_roadmap_ignores_inactive_roadmaps(): void
    {
        ['user' => $user] = $this->createJobSeekerWithCv();

        Roadmap::create([
            'user_id' => $user->UserID,
            'title' => 'Old Roadmap',
            'target_role' => 'Senior Developer',
            'generated_at' => now(),
            'is_active' => false,
        ]);

        $response = $this->actingAs($user)->getJson('/api/career-roadmap');

        $response->assertStatus(404);
    }
}
