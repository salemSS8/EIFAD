<?php

namespace App\Domain\Application\Jobs;

use App\Domain\Application\Models\JobApplication;
use App\Domain\AI\Models\CVJobMatch;
use App\Domain\Shared\Services\GeminiAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job: Explain Compatibility using Gemini AI.
 * 
 * This job uses Gemini ONLY for semantic interpretation.
 * 
 * Input:
 * - Compatibility scores (already calculated by ComputeCompatibilityJob)
 * - Match breakdown
 * 
 * Output (TEXT ONLY):
 * - explanation: why compatibility is HIGH/MEDIUM/LOW
 * - strengths: candidate strengths for this role
 * - gaps: areas where candidate doesn't match
 * 
 * Constraints:
 * ❌ NO hiring decisions (strong_hire, hire, maybe, no_hire)
 * ❌ NO numeric scoring
 * ❌ NO parsing
 */
class ExplainCompatibilityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public JobApplication $application
    ) {}

    /**
     * Execute the job.
     */
    public function handle(GeminiAIService $aiService): array
    {
        try {
            // Load related data
            $this->application->load([
                'cv.skills.skill',
                'cv.experiences',
                'jobAd.skills.skill',
            ]);

            // Get match record
            $match = CVJobMatch::where('CVID', $this->application->CVID)
                ->where('JobAdID', $this->application->JobAdID)
                ->first();

            if (!$match || !$match->MatchScore) {
                Log::warning('ExplainCompatibilityJob: No compatibility scores found', [
                    'application_id' => $this->application->ApplicationID
                ]);
                return ['success' => false, 'error' => 'Compatibility must be computed first'];
            }

            // Build context for explanation
            $context = $this->buildExplanationContext($match);

            // Get AI explanation (text only)
            $explanation = $aiService->explainCompatibility($context);

            // Persist explanation
            $this->persistExplanation($match, $explanation);

            Log::info('ExplainCompatibilityJob: Compatibility explained', [
                'application_id' => $this->application->ApplicationID,
            ]);

            return [
                'success' => true,
                'explanation' => $explanation,
            ];
        } catch (\Exception $e) {
            Log::error('ExplainCompatibilityJob failed', [
                'application_id' => $this->application->ApplicationID,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Build context for AI explanation.
     */
    private function buildExplanationContext(CVJobMatch $match): array
    {
        $cv = $this->application->cv;
        $job = $this->application->jobAd;

        return [
            'job_title' => $job->Title,
            'job_requirements' => $job->Requirements,
            'candidate_skills' => $cv->skills->map(fn($s) => $s->skill->SkillName ?? '')->toArray(),
            'required_skills' => $job->skills->map(fn($s) => $s->skill->SkillName ?? '')->toArray(),
            'experience_count' => $cv->experiences->count(),
            'compatibility_level' => $match->CompatibilityLevel,
            'scores' => [
                'total' => $match->MatchScore,
                'skills' => $match->SkillsScore,
                'experience' => $match->ExperienceScore,
                'education' => $match->EducationScore,
            ],
        ];
    }

    /**
     * Persist AI explanation to database.
     */
    private function persistExplanation(CVJobMatch $match, array $explanation): void
    {
        $match->update([
            'Explanation' => $explanation['explanation'] ?? null,
            'Strengths' => json_encode($explanation['strengths'] ?? []),
            'Gaps' => json_encode($explanation['gaps'] ?? []),
            'AIModel' => config('gemini.model'),
            'ExplainedAt' => now(),
        ]);
    }
}
