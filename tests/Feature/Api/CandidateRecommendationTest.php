<?php

namespace Tests\Feature\Api;

use App\Domain\User\Models\User;
use App\Domain\User\Models\Role;
use App\Domain\Company\Models\CompanyProfile;
use App\Domain\Job\Models\JobAd;
use App\Domain\CV\Models\CV;
use App\Domain\AI\Models\CVJobMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateRecommendationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['RoleID' => 2, 'RoleName' => 'Employer']);
    }

    public function test_employer_can_get_recommendations_for_their_job(): void
    {
        $employerUser = User::factory()->create();
        $company = CompanyProfile::create([
            'CompanyID' => $employerUser->UserID,
            'CompanyName' => 'Test Corp'
        ]);

        $job = JobAd::create([
            'CompanyID' => $company->CompanyID,
            'Title' => 'Backend Developer',
            'Status' => 'Active'
        ]);

        $candidateUser = User::factory()->create(['FullName' => 'Top Candidate']);
        $jobSeeker = \App\Domain\User\Models\JobSeekerProfile::create(['JobSeekerID' => $candidateUser->UserID]);
        $cv = CV::create(['JobSeekerID' => $jobSeeker->JobSeekerID, 'Title' => 'My CV']);

        // Create a match
        CVJobMatch::create([
            'CVID' => $cv->CVID,
            'JobAdID' => $job->JobAdID,
            'MatchScore' => 85,
            'MatchDate' => now(),
            'Strengths' => ['PHP', 'Laravel'],
            'Gaps' => ['Python'],
            'Explanation' => 'Good fit'
        ]);

        $response = $this->actingAs($employerUser)->getJson("/api/employer/jobs/{$job->JobAdID}/recommendations");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.match_score', 85)
            ->assertJsonPath('data.0.candidate.name', 'Top Candidate');
    }

    public function test_cannot_get_recommendations_for_other_employer_job(): void
    {
        $employer1 = User::factory()->create();
        CompanyProfile::create(['CompanyID' => $employer1->UserID]);
        
        $employer2 = User::factory()->create();
        CompanyProfile::create(['CompanyID' => $employer2->UserID]);

        $job = JobAd::create([
            'CompanyID' => $employer1->UserID,
            'Title' => 'Job 1'
        ]);

        $response = $this->actingAs($employer2)->getJson("/api/employer/jobs/{$job->JobAdID}/recommendations");

        $response->assertStatus(404); // firstOrFail on ownership check
    }
}
