<?php

namespace App\Domain\Application\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CandidateScore Model - AI-generated candidate scoring for applications.
 */
class CandidateScore extends Model
{
    protected $fillable = [
        'application_id',
        'overall_score',
        'skill_match_score',
        'experience_match_score',
        'education_match_score',
        'strengths',
        'concerns',
        'recommendation',
        'analysis_details',
        'scored_at',
        'score_version',
    ];

    protected function casts(): array
    {
        return [
            'strengths' => 'array',
            'concerns' => 'array',
            'analysis_details' => 'array',
            'scored_at' => 'datetime',
        ];
    }

    /**
     * Get the application this score belongs to.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
