<?php

namespace Tests\Feature\CV;

use App\Domain\CV\DTOs\CanonicalResumeDTO;
use App\Domain\CV\Jobs\AnalyzeCVJob;
use App\Domain\CV\Jobs\ExplainCvAnalysisWithGeminiJob;
use App\Domain\CV\Models\CV;
use App\Domain\CV\Services\AffindaResumeParser;
use App\Domain\CV\Services\CanonicalResumeMapper;
use App\Domain\CV\Services\CvScoringRubric;
use App\Domain\User\Models\Role;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnalyzeCVJobTest extends TestCase
{
    use RefreshDatabase;

    protected User $jobSeeker;

    protected int $cvId;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['RoleName' => 'JobSeeker']);

        $this->jobSeeker = User::factory()->create();
        $this->jobSeeker->roles()->attach(Role::where('RoleName', 'JobSeeker')->first());

        DB::table('jobseekerprofile')->insert([
            'JobSeekerID' => $this->jobSeeker->UserID,
        ]);

        $this->cvId = DB::table('cv')->insertGetId([
            'JobSeekerID' => $this->jobSeeker->UserID,
            'Title' => 'Software Developer CV',
            'CreatedAt' => now(),
        ]);
    }

    /**
     * Create a mock CanonicalResumeDTO for testing.
     */
    private function makeMockDto(): CanonicalResumeDTO
    {
        return new CanonicalResumeDTO(
            fullName: 'Test User',
            email: 'test@example.com',
            skills: [],
            experiences: [],
            education: [],
            languages: [],
            rawContent: 'Test content',
        );
    }

    /**
     * Mock the services used by ParseCvJob and ScoreCvRuleBasedJob.
     */
    private function mockPipelineServices(): void
    {
        $mockParser = $this->createMock(AffindaResumeParser::class);
        $mockParser->method('isAvailable')->willReturn(false);
        $this->app->instance(AffindaResumeParser::class, $mockParser);

        $dto = $this->makeMockDto();
        $mockMapper = $this->createMock(CanonicalResumeMapper::class);
        $mockMapper->method('fromCvModel')->willReturn($dto);
        $mockMapper->method('fromRegexParsedData')->willReturn($dto);
        $this->app->instance(CanonicalResumeMapper::class, $mockMapper);

        $mockRubric = $this->createMock(CvScoringRubric::class);
        $mockRubric->method('calculateScore')->willReturn([
            'total_score' => 65,
            'breakdown' => [
                'skills' => ['score' => 60],
                'experience' => ['score' => 70],
                'education' => ['score' => 65],
                'completeness' => ['score' => 55],
                'consistency' => ['score' => 75],
            ],
        ]);
        $this->app->instance(CvScoringRubric::class, $mockRubric);
    }

    public function test_first_cv_runs_full_pipeline_with_parse_and_score(): void
    {
        Queue::fake();
        $this->mockPipelineServices();

        $cv = CV::find($this->cvId);
        $job = new AnalyzeCVJob($cv);
        $job->handle();

        $this->assertDatabaseHas('cv_analyses', [
            'CVID' => $this->cvId,
            'OverallScore' => 65,
            'ScoringMethod' => 'rule_based',
        ]);

        Queue::assertPushed(ExplainCvAnalysisWithGeminiJob::class, function ($job) {
            return $job->cv->CVID === $this->cvId;
        });
    }

    public function test_existing_cv_user_skips_parse_and_only_scores(): void
    {
        Queue::fake();
        $this->mockPipelineServices();

        // Create an older CV so user "already has CVs"
        DB::table('cv')->insert([
            'JobSeekerID' => $this->jobSeeker->UserID,
            'Title' => 'Old CV',
            'CreatedAt' => now()->subMonth(),
        ]);

        $cv = CV::find($this->cvId);
        $job = new AnalyzeCVJob($cv);
        $job->handle();

        $this->assertDatabaseHas('cv_analyses', [
            'CVID' => $this->cvId,
            'OverallScore' => 65,
        ]);

        Queue::assertPushed(ExplainCvAnalysisWithGeminiJob::class);
    }

    public function test_skip_explanation_flag_prevents_ai_dispatch(): void
    {
        Queue::fake();
        $this->mockPipelineServices();

        $cv = CV::find($this->cvId);
        $job = new AnalyzeCVJob($cv, skipExplanation: true);
        $job->handle();

        $this->assertDatabaseHas('cv_analyses', [
            'CVID' => $this->cvId,
        ]);

        Queue::assertNotPushed(ExplainCvAnalysisWithGeminiJob::class);
    }

    public function test_job_returns_early_when_user_not_found(): void
    {
        Queue::fake();
        Log::shouldReceive('info')->atLeast()->once();
        Log::shouldReceive('error')->once()->withArgs(function ($message) {
            return str_contains($message, 'User or JobSeekerProfile not found');
        });

        // Create a CV then manually set JobSeekerID to non-existent value on the model
        $cv = CV::find($this->cvId);
        $cv->JobSeekerID = 99999; // No user with this ID exists

        $job = new AnalyzeCVJob($cv);
        $job->handle();

        $this->assertDatabaseMissing('cv_analyses', [
            'CVID' => $this->cvId,
        ]);

        Queue::assertNotPushed(ExplainCvAnalysisWithGeminiJob::class);
    }

    public function test_job_returns_early_when_profile_not_found(): void
    {
        Queue::fake();
        Log::shouldReceive('info')->atLeast()->once();
        Log::shouldReceive('error')->once()->withArgs(function ($message) {
            return str_contains($message, 'User or JobSeekerProfile not found');
        });

        // Create user WITHOUT a jobseekerprofile
        $userNoProfile = User::factory()->create();

        // Point the CV model's JobSeekerID to this user (no profile)
        $cv = CV::find($this->cvId);
        $cv->JobSeekerID = $userNoProfile->UserID;

        $job = new AnalyzeCVJob($cv);
        $job->handle();

        $this->assertDatabaseMissing('cv_analyses', [
            'CVID' => $this->cvId,
        ]);

        Queue::assertNotPushed(ExplainCvAnalysisWithGeminiJob::class);
    }

    public function test_job_retries_on_exception(): void
    {
        $cv = CV::find($this->cvId);
        $job = new AnalyzeCVJob($cv);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(60, $job->backoff);
    }

    public function test_pipeline_creates_analysis_even_when_parse_fails(): void
    {
        Queue::fake();

        $mockParser = $this->createMock(AffindaResumeParser::class);
        $mockParser->method('isAvailable')->willReturn(false);
        $this->app->instance(AffindaResumeParser::class, $mockParser);

        $emptyDto = new CanonicalResumeDTO(
            skills: [],
            experiences: [],
            education: [],
            languages: [],
            rawContent: '',
        );
        $mockMapper = $this->createMock(CanonicalResumeMapper::class);
        $mockMapper->method('fromCvModel')->willReturn($emptyDto);
        $mockMapper->method('fromRegexParsedData')->willReturn($emptyDto);
        $this->app->instance(CanonicalResumeMapper::class, $mockMapper);

        $mockRubric = $this->createMock(CvScoringRubric::class);
        $mockRubric->method('calculateScore')->willReturn([
            'total_score' => 30,
            'breakdown' => [
                'skills' => ['score' => 0],
                'experience' => ['score' => 0],
                'education' => ['score' => 0],
                'completeness' => ['score' => 50],
                'consistency' => ['score' => 100],
            ],
        ]);
        $this->app->instance(CvScoringRubric::class, $mockRubric);

        $cv = CV::find($this->cvId);
        $job = new AnalyzeCVJob($cv);
        $job->handle();

        $this->assertDatabaseHas('cv_analyses', [
            'CVID' => $this->cvId,
            'OverallScore' => 30,
        ]);
    }
}
