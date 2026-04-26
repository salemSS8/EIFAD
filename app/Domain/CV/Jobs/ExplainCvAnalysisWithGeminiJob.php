<?php

namespace App\Domain\CV\Jobs;

use App\Domain\CV\Models\CV;
use App\Domain\CV\Models\CVAnalysis;
use App\Domain\Shared\Services\GeminiAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job: Explain CV Analysis using Gemini AI.
 *
 * This job uses Gemini ONLY for semantic interpretation and explanation.
 *
 * Input:
 * - Canonical CV data (already extracted)
 * - Rule-based scores (already calculated)
 *
 * Output (TEXT ONLY):
 * - strengths: textual description
 * - potential_gaps: textual description
 * - improvement_recommendations: textual description
 *
 * Constraints:
 * ❌ NO numeric scoring
 * ❌ NO parsing
 * ❌ NO decisions
 */
class ExplainCvAnalysisWithGeminiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 10;

    /**
     * Get the backoff strategy for the job.
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120, 240, 480];
    }

    public function __construct(
        public CV $cv
    ) {}

    /**
     * Execute the job.
     */
    public function handle(GeminiAIService $aiService): array
    {
        try {
            // Load CV with analysis (must be scored first)
            $this->cv->load(['skills.skill', 'education', 'experiences', 'analysis']);

            $analysis = $this->cv->analysis;

            if (! $analysis || ! $analysis->OverallScore) {
                Log::warning('ExplainCvAnalysisWithGeminiJob: No scores found, skipping', [
                    'cv_id' => $this->cv->CVID,
                ]);

                return ['success' => false, 'error' => 'CV must be scored first'];
            }

            // Build context for explanation
            $context = $this->buildExplanationContext($analysis);

            // Get AI explanation (text only)
            $explanation = $aiService->explainCvAnalysis($context);

            // Persist explanation
            $this->persistExplanation($explanation);

            Log::info('ExplainCvAnalysisWithGeminiJob: CV explained successfully', [
                'cv_id' => $this->cv->CVID,
            ]);

            return [
                'success' => true,
                'explanation' => $explanation,
            ];
        } catch (\Exception $e) {
            Log::error('ExplainCvAnalysisWithGeminiJob failed', [
                'cv_id' => $this->cv->CVID,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Build context for AI explanation.
     */
    private function buildExplanationContext(CVAnalysis $analysis): array
    {
        return [
            'cv_title' => $this->cv->Title,
            'personal_summary' => $this->cv->PersonalSummary,
            'skills_count' => $this->cv->skills->count(),
            'skills_list' => $this->cv->skills->map(fn ($s) => $s->skill->SkillName ?? '')->toArray(),
            'experience_count' => $this->cv->experiences->count(),
            'education_count' => $this->cv->education->count(),
            'scores' => [
                'overall' => $analysis->OverallScore,
                'skills' => $analysis->SkillsScore,
                'experience' => $analysis->ExperienceScore,
                'education' => $analysis->EducationScore,
                'completeness' => $analysis->CompletenessScore,
                'consistency' => $analysis->ConsistencyScore,
            ],
        ];
    }

    private function persistExplanation(array $explanation): void
    {
        $analysis = CVAnalysis::where('CVID', $this->cv->CVID)->first();

        if (! $analysis) {
            return;
        }

        // Helper to extract text and wrap in array for JSON columns
        $extract = function ($key) use ($explanation) {
            $val = $explanation[$key] ?? null;
            if (is_array($val)) {
                $val = $val['en'] ?? $val['ar'] ?? array_values($val)[0] ?? null;
            }

            return $val ? [$val] : [];
        };

        $analysis->update([
            'strengths' => $extract('strengths'),
            'PotentialGaps' => $extract('potential_gaps'),
            'ImprovementRecommendations' => $extract('improvement_recommendations'),
            'AIExplanation' => $explanation,
            'AIModel' => config('gemini.model'),
            'ExplainedAt' => now(),
        ]);
    }
}
