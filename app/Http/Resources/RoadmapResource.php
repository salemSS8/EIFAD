<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for Career Roadmap results.
 */
class RoadmapResource extends JsonResource
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
            'user_id' => $this->user_id,
            'title' => $this->title,
            'target_role' => $this->target_role,
            'current_level' => $this->current_level,
            'target_level' => $this->target_level,
            'milestones' => $this->milestones,
            'total_estimated_time' => $this->total_estimated_time,
            'generated_at' => $this->generated_at,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
