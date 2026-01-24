<?php

namespace App\Domain\Course\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SkillSuggestion Model - AI-generated skill gap suggestions.
 */
class SkillSuggestion extends Model
{
    protected $fillable = [
        'user_id',
        'cv_analysis_id',
        'target_role',
        'missing_skills',
        'skills_to_improve',
        'recommended_courses',
        'estimated_learning_time',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'missing_skills' => 'array',
            'skills_to_improve' => 'array',
            'recommended_courses' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    /**
     * Get the user this suggestion belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class);
    }

    /**
     * Get the CV analysis this suggestion is based on.
     */
    public function cvAnalysis(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\CV\Models\CVAnalysis::class);
    }
}
