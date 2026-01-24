<?php

namespace App\Domain\Application\Jobs;

use App\Domain\Application\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Candidate Evaluation Orchestrator Job.
 * 
 * This job orchestrates the candidate evaluation pipeline:
 * 
 * 1. ComputeCompatibilityJob → Calculate compatibility (NO AI, NO hiring decisions)
 * 2. ExplainCompatibilityJob → Generate explanation (AI - text only)
 * 
 * ⚠️ IMPORTANT: NO hiring decisions (strong_hire, hire, maybe, no_hire)
 * Only compatibility levels: HIGH | MEDIUM | LOW
 */
class EvaluateCandidateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public JobApplication $application,
        public bool $skipExplanation = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('EvaluateCandidateJob: Starting evaluation pipeline', [
                'application_id' => $this->application->ApplicationID
            ]);

            // Step 1: Compute Compatibility (Rule-based - NO AI)
            $compatibilityJob = new ComputeCompatibilityJob($this->application);
            $result = $compatibilityJob->handle();

            if (!($result['success'] ?? false)) {
                Log::warning('EvaluateCandidateJob: Compatibility computation failed', [
                    'application_id' => $this->application->ApplicationID,
                    'error' => $result['error'] ?? 'Unknown'
                ]);
                return;
            }

            $compatibilityLevel = $result['result']['compatibility_level'] ?? 'UNKNOWN';
            $totalScore = $result['result']['total_score'] ?? 0;

            Log::info('EvaluateCandidateJob: Compatibility computed', [
                'application_id' => $this->application->ApplicationID,
                'compatibility_level' => $compatibilityLevel,
                'total_score' => $totalScore,
            ]);

            // Step 2: Explain with AI (Optional - TEXT ONLY)
            if (!$this->skipExplanation) {
                // Dispatch explanation job to queue (async)
                ExplainCompatibilityJob::dispatch($this->application->fresh())
                    ->delay(now()->addSeconds(5));

                Log::info('EvaluateCandidateJob: AI explanation job dispatched', [
                    'application_id' => $this->application->ApplicationID
                ]);
            }

            Log::info('EvaluateCandidateJob: Pipeline completed', [
                'application_id' => $this->application->ApplicationID,
                'compatibility_level' => $compatibilityLevel,
            ]);
        } catch (\Exception $e) {
            Log::error('EvaluateCandidateJob: Pipeline failed', [
                'application_id' => $this->application->ApplicationID,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
