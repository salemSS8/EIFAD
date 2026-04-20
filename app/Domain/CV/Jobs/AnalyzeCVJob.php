<?php

namespace App\Domain\CV\Jobs;

use App\Domain\CV\Models\CV;
use App\Domain\User\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * CV Analysis Orchestrator Job.
 *
 * This job orchestrates the CV analysis pipeline:
 *
 * 1. ParseCvJob → Extract data (NO AI)
 * 2. ScoreCvRuleBasedJob → Calculate scores (NO AI)
 * 3. ExplainCvAnalysisWithGeminiJob → Generate explanation (AI - text only)
 *
 * This replaces the old monolithic AI-based analysis.
 */
class AnalyzeCVJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public CV $cv,
        public bool $skipExplanation = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('AnalyzeCVJob: Starting CV analysis pipeline', [
                'cv_id' => $this->cv->CVID,
            ]);

            $user = User::where('UserID', $this->cv->JobSeekerID)->first();

            if (! $user || ! $user->jobSeekerProfile) {
                Log::error('AnalyzeCVJob: User or JobSeekerProfile not found', [
                    'cv_id' => $this->cv->CVID,
                    'job_seeker_id' => $this->cv->JobSeekerID,
                ]);

                return;
            }

            // If user already has other CVs, skip Affinda parsing and just re-score
            if ($user->jobSeekerProfile->cvs()->where('CVID', '!=', $this->cv->CVID)->exists()) {
                Log::info('AnalyzeCVJob: User already has CVs, skipping parse — scoring only', [
                    'cv_id' => $this->cv->CVID,
                ]);

                $scoreJob = new ScoreCvRuleBasedJob($this->cv->fresh());
                $rubric = app(\App\Domain\CV\Services\CvScoringRubric::class);
                $scoreResult = $scoreJob->handle($rubric);

                Log::info('AnalyzeCVJob: Rule-based scoring completed', [
                    'cv_id' => $this->cv->CVID,
                    'total_score' => $scoreResult['total_score'] ?? 'N/A',
                ]);
            } else {
                // Step 1: Parse CV (Extract structured data - NO AI)
                $parseJob = new ParseCvJob($this->cv);
                $affindaParser = app(\App\Domain\CV\Services\AffindaResumeParser::class);
                $mapper = app(\App\Domain\CV\Services\CanonicalResumeMapper::class);
                $parseResult = $parseJob->handle($affindaParser, $mapper);

                if (! ($parseResult['success'] ?? false)) {
                    Log::warning('AnalyzeCVJob: Parsing failed', [
                        'cv_id' => $this->cv->CVID,
                        'error' => $parseResult['error'] ?? 'Unknown',
                    ]);
                    // Continue anyway, scoring can work with existing data
                }

                // Step 2: Score CV (Rule-based scoring - NO AI)
                $scoreJob = new ScoreCvRuleBasedJob($this->cv->fresh());
                $rubric = app(\App\Domain\CV\Services\CvScoringRubric::class);
                $scoreResult = $scoreJob->handle($rubric);

                Log::info('AnalyzeCVJob: Rule-based scoring completed', [
                    'cv_id' => $this->cv->CVID,
                    'total_score' => $scoreResult['total_score'] ?? 'N/A',
                ]);
            }

            // Step 3: Explain with AI (Optional - TEXT ONLY)
            if (! $this->skipExplanation) {
                ExplainCvAnalysisWithGeminiJob::dispatch($this->cv->fresh())
                    ->delay(now()->addSeconds(5));

                Log::info('AnalyzeCVJob: AI explanation job dispatched', [
                    'cv_id' => $this->cv->CVID,
                ]);
            }

            Log::info('AnalyzeCVJob: Pipeline completed successfully', [
                'cv_id' => $this->cv->CVID,
            ]);
        } catch (\Exception $e) {
            Log::error('AnalyzeCVJob: Pipeline failed', [
                'cv_id' => $this->cv->CVID,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
