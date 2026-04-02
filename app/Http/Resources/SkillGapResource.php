<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for Skill Gap Analysis results.
 */
class SkillGapResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'gap_id' => $this->GapID,
            'cv_id' => $this->CVID,
            'job_ad_id' => $this->JobAdID,
            'skill' => $this->whenLoaded('skill', fn () => [
                'id' => $this->skill->SkillID,
                'name' => $this->skill->SkillName,
            ]),
            'cv_level' => $this->CVLevel,
            'required_level' => $this->RequiredLevel,
            'gap_type' => $this->GapType,
            'job_ad' => $this->whenLoaded('jobAd', fn () => [
                'id' => $this->jobAd->JobAdID,
                'title' => $this->jobAd->Title,
            ]),
        ];
    }
}
