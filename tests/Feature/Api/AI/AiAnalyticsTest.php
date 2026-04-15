<?php

namespace Tests\Feature\Api\AI;

use App\Domain\User\Models\Role;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AiAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $employer;

    protected User $jobSeeker;

    protected int $cvId;

    protected int $jobAdId;

    protected int $applicationId;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['RoleName' => 'Employer']);
        Role::create(['RoleName' => 'JobSeeker']);

        // Create employer
        $this->employer = User::factory()->create();
        $this->employer->roles()->attach(Role::where('RoleName', 'Employer')->first());
        DB::table('companyprofile')->insert([
            'CompanyID' => $this->employer->UserID,
            'CompanyName' => 'Tech Corp',
        ]);

        // Create job seeker
        $this->jobSeeker = User::factory()->create();
        $this->jobSeeker->roles()->attach(Role::where('RoleName', 'JobSeeker')->first());
        DB::table('jobseekerprofile')->insert([
            'JobSeekerID' => $this->jobSeeker->UserID,
        ]);

        // Create CV
        $this->cvId = DB::table('cv')->insertGetId([
            'JobSeekerID' => $this->jobSeeker->UserID,
            'Title' => 'Backend Developer CV',
            'CreatedAt' => now(),
        ]);

        // Create job ad
        $this->jobAdId = DB::table('jobad')->insertGetId([
            'CompanyID' => $this->employer->UserID,
            'Title' => 'Backend Developer',
            'Description' => 'Laravel developer needed',
            'Status' => 'Active',
            'PostedAt' => now(),
        ]);

        // Create application
        $this->applicationId = DB::table('jobapplication')->insertGetId([
            'JobAdID' => $this->jobAdId,
            'JobSeekerID' => $this->jobSeeker->UserID,
            'CVID' => $this->cvId,
            'AppliedAt' => now(),
            'Status' => 'Pending',
            'MatchScore' => 75,
            'Notes' => 'Good match for the position',
        ]);
    }

    // ==========================================
    // AI Match Endpoint Tests
    // ==========================================

    public function test_employer_can_view_ai_match(): void
    {
        $response = $this->actingAs($this->employer)
            ->getJson("/api/applications/{$this->applicationId}/ai-match");

        $response->assertStatus(200)
            ->assertJsonPath('data.application_id', $this->applicationId)
            ->assertJsonPath('data.match_score', 75)
            ->assertJsonPath('data.notes', 'Good match for the position');
    }

    public function test_non_owner_employer_cannot_view_ai_match(): void
    {
        $otherEmployer = User::factory()->create();
        $otherEmployer->roles()->attach(Role::where('RoleName', 'Employer')->first());
        DB::table('companyprofile')->insert(['CompanyID' => $otherEmployer->UserID]);

        $response = $this->actingAs($otherEmployer)
            ->getJson("/api/applications/{$this->applicationId}/ai-match");

        $response->assertStatus(403);
    }

    public function test_ai_match_returns_404_for_invalid_application(): void
    {
        $response = $this->actingAs($this->employer)
            ->getJson('/api/applications/99999/ai-match');

        $response->assertStatus(404);
    }

    public function test_ai_match_includes_cv_job_match_when_available(): void
    {
        DB::table('cvjobmatch')->insert([
            'CVID' => $this->cvId,
            'JobAdID' => $this->jobAdId,
            'MatchScore' => 75,
            'CompatibilityLevel' => 'HIGH',
            'SkillsScore' => 80,
            'ExperienceScore' => 70,
            'EducationScore' => 75,
            'ScoringMethod' => 'rule-based',
        ]);

        $response = $this->actingAs($this->employer)
            ->getJson("/api/applications/{$this->applicationId}/ai-match");

        $response->assertStatus(200)
            ->assertJsonPath('data.cv_job_match.compatibility_level', 'HIGH')
            ->assertJsonPath('data.cv_job_match.skills_score', 80);
    }

    // ==========================================
    // CV Analysis Endpoint Tests
    // ==========================================

    public function test_job_seeker_can_view_cv_analysis(): void
    {
        DB::table('cv_analyses')->insert([
            'CVID' => $this->cvId,
            'cv_id' => $this->cvId,
            'OverallScore' => 85,
            'SkillsScore' => 90,
            'ExperienceScore' => 80,
            'EducationScore' => 85,
            'ScoringMethod' => 'rule-based',
        ]);

        $response = $this->actingAs($this->jobSeeker)
            ->getJson("/api/cvs/{$this->cvId}/analysis");

        $response->assertStatus(200)
            ->assertJsonPath('data.scores.overall', 85)
            ->assertJsonPath('data.scores.skills', 90);
    }

    public function test_cv_analysis_returns_404_when_no_analysis(): void
    {
        $response = $this->actingAs($this->jobSeeker)
            ->getJson("/api/cvs/{$this->cvId}/analysis");

        $response->assertStatus(404)
            ->assertJsonPath('message', 'No analysis found for this CV');
    }

    public function test_other_user_cannot_view_cv_analysis(): void
    {
        DB::table('cv_analyses')->insert([
            'CVID' => $this->cvId,
            'cv_id' => $this->cvId,
            'OverallScore' => 85,
        ]);

        $response = $this->actingAs($this->employer)
            ->getJson("/api/cvs/{$this->cvId}/analysis");

        $response->assertStatus(403);
    }

    // ==========================================
    // Skill Gaps Endpoint Tests
    // ==========================================

    public function test_job_seeker_can_view_skill_gaps(): void
    {
        $skillId = DB::table('skill')->insertGetId([
            'SkillName' => 'Docker',
            'CategoryID' => null,
        ]);

        DB::table('skillgapanalysis')->insert([
            'CVID' => $this->cvId,
            'JobAdID' => $this->jobAdId,
            'SkillID' => $skillId,
            'CVLevel' => 'Beginner',
            'RequiredLevel' => 'Advanced',
            'GapType' => 'MISSING',
        ]);

        $response = $this->actingAs($this->jobSeeker)
            ->getJson("/api/cvs/{$this->cvId}/skill-gaps");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.skill.name', 'Docker')
            ->assertJsonPath('data.0.gap_type', 'MISSING')
            ->assertJsonPath('summary.total_gaps', 1);
    }

    public function test_skill_gaps_can_filter_by_job_ad(): void
    {
        $skillId = DB::table('skill')->insertGetId(['SkillName' => 'Kubernetes']);

        DB::table('skillgapanalysis')->insert([
            ['CVID' => $this->cvId, 'JobAdID' => $this->jobAdId, 'SkillID' => $skillId, 'CVLevel' => 'None', 'RequiredLevel' => 'Mid', 'GapType' => 'MISSING'],
        ]);

        // Create another job and gap
        $otherJobId = DB::table('jobad')->insertGetId([
            'CompanyID' => $this->employer->UserID,
            'Title' => 'DevOps',
            'Status' => 'Active',
        ]);

        $skillId2 = DB::table('skill')->insertGetId(['SkillName' => 'AWS']);

        DB::table('skillgapanalysis')->insert([
            ['CVID' => $this->cvId, 'JobAdID' => $otherJobId, 'SkillID' => $skillId2, 'CVLevel' => 'None', 'RequiredLevel' => 'Senior', 'GapType' => 'MISSING'],
        ]);

        // Filter by specific job ad
        $response = $this->actingAs($this->jobSeeker)
            ->getJson("/api/cvs/{$this->cvId}/skill-gaps?job_ad_id={$this->jobAdId}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_other_user_cannot_view_skill_gaps(): void
    {
        $response = $this->actingAs($this->employer)
            ->getJson("/api/cvs/{$this->cvId}/skill-gaps");

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_ai_endpoints(): void
    {
        $this->getJson("/api/applications/{$this->applicationId}/ai-match")
            ->assertStatus(401);

        $this->getJson("/api/cvs/{$this->cvId}/analysis")
            ->assertStatus(401);

        $this->getJson("/api/cvs/{$this->cvId}/skill-gaps")
            ->assertStatus(401);
    }

    // ==========================================
    // Market Trends Endpoint Tests
    // ==========================================

    public function test_authenticated_user_can_view_market_trends(): void
    {
        $skillId = DB::table('skill')->insertGetId([
            'SkillName' => 'Laravel',
            'CategoryID' => null,
        ]);

        DB::table('skilldemandsnapshot')->insert([
            'SkillID' => $skillId,
            'DemandCount' => 100,
            'SnapshotDate' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->jobSeeker)
            ->getJson('/api/market-trends');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'SnapshotID',
                        'SkillID',
                        'DemandCount',
                        'SnapshotDate',
                    ]
                ],
                'meta' => [
                    'snapshot_date'
                ]
            ]);
    }
}
