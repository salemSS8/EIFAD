<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for AI Match details of a job application.
 */
class AiMatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'application_id' => $this->ApplicationID,
            'job_ad_id' => $this->JobAdID,
            'job_seeker_id' => $this->JobSeekerID,
            'match_score' => $this->MatchScore,
            'notes' => $this->Notes,
            'status' => $this->Status,
            'applied_at' => $this->AppliedAt,
            'job_ad' => $this->whenLoaded('jobAd', fn () => [
                'title' => $this->jobAd->Title,
                'company' => $this->whenLoaded('jobAd.company', fn () => $this->jobAd->company?->CompanyName),
            ]),
            'cv_job_match' => $this->when($this->relationLoaded('cvJobMatch') && $this->cvJobMatch, function () {
                $match = $this->cvJobMatch;

                return [
                    'compatibility_level' => $match->CompatibilityLevel,
                    'skills_score' => $match->SkillsScore,
                    'experience_score' => $match->ExperienceScore,
                    'education_score' => $match->EducationScore,
                    'score_breakdown' => $match->ScoreBreakdown,
                    'explanation' => $match->Explanation,
                    'strengths' => $match->Strengths,
                    'gaps' => $match->Gaps,
                    'scoring_method' => $match->ScoringMethod,
                    'ai_model' => $match->AIModel,
                    'details' => $match->relationLoaded('details')
                        ? $match->details->map(fn ($d) => [
                            'skill' => $d->skill?->SkillName,
                            'cv_level' => $d->CVLevel,
                            'required_level' => $d->RequiredLevel,
                            'is_matched' => $d->IsMatched,
                        ])
                        : null,
                ];
            }),
        ];
    }
}
