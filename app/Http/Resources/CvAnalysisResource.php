<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for CV Analysis results.
 */
class CvAnalysisResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cv_id' => $this->CVID ?? $this->cv_id,
            'scores' => [
                'overall' => $this->OverallScore ?? $this->overall_score,
                'skills' => $this->SkillsScore,
                'experience' => $this->ExperienceScore,
                'education' => $this->EducationScore,
                'completeness' => $this->CompletenessScore,
                'consistency' => $this->ConsistencyScore,
            ],
            'score_breakdown' => $this->ScoreBreakdown,
            'scoring_method' => $this->ScoringMethod,
            'strengths' => $this->Strengths ?? $this->strengths,
            'potential_gaps' => $this->PotentialGaps,
            'improvement_recommendations' => $this->ImprovementRecommendations,
            'ai_explanation' => $this->AIExplanation,
            'ai_model' => $this->AIModel,
            'analyzed_at' => $this->analyzed_at,
            'scored_at' => $this->ScoredAt,
            'explained_at' => $this->ExplainedAt,
        ];
    }
}
