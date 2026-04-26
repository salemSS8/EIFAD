<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CandidateRecommendationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // $this is the CVJobMatch model
        return [
            'id' => $this->MatchID,
            'match_score' => $this->MatchScore,
            'scores' => [
                'skills' => $this->SkillsScore,
                'experience' => $this->ExperienceScore,
                'education' => $this->EducationScore,
            ],
            'strengths' => $this->Strengths,
            'gaps' => $this->Gaps,
            'explanation' => $this->Explanation,
            'calculated_at' => $this->CalculatedAt,
            'candidate' => [
                'id' => $this->cv->JobSeekerID,
                'cv_id' => $this->CVID,
                'name' => $this->cv->jobSeeker->user->FullName,
                'email' => $this->cv->jobSeeker->user->Email,
                'phone' => $this->cv->jobSeeker->user->Phone,
                'avatar' => $this->cv->jobSeeker->user->Avatar,
                'location' => $this->cv->jobSeeker->Location,
            ],
        ];
    }
}
