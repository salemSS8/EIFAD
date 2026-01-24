<?php

namespace App\Domain\CV\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CVAnalysis Model - Stores CV analysis results.
 * 
 * Supports both:
 * - Rule-based scoring (no AI)
 * - AI-generated explanations (Gemini)
 */
class CVAnalysis extends Model
{
    protected $table = 'cv_analyses';

    protected $fillable = [
        // CV Reference
        'cv_id',
        'CVID',

        // Legacy fields (for backward compatibility)
        'personal_info',
        'summary',
        'skills',
        'experience',
        'education',
        'certifications',
        'languages',
        'raw_response',
        'analyzed_at',
        'analysis_version',

        // Rule-Based Scores (NO AI)
        'OverallScore',
        'SkillsScore',
        'ExperienceScore',
        'EducationScore',
        'CompletenessScore',
        'ConsistencyScore',
        'ScoreBreakdown',
        'ScoringMethod',
        'ScoredAt',

        // AI Explanations (Gemini - TEXT ONLY)
        'Strengths',
        'PotentialGaps',
        'ImprovementRecommendations',
        'AIExplanation',
        'AIModel',
        'ExplainedAt',

        // Legacy
        'overall_score',
        'strengths',
        'areas_for_improvement',
    ];

    protected function casts(): array
    {
        return [
            'personal_info' => 'array',
            'skills' => 'array',
            'experience' => 'array',
            'education' => 'array',
            'certifications' => 'array',
            'languages' => 'array',
            'strengths' => 'array',
            'areas_for_improvement' => 'array',
            'ScoreBreakdown' => 'array',
            'analyzed_at' => 'datetime',
            'ScoredAt' => 'datetime',
            'ExplainedAt' => 'datetime',
        ];
    }

    /**
     * Get the CV that this analysis belongs to.
     */
    public function cv(): BelongsTo
    {
        return $this->belongsTo(CV::class, 'CVID', 'CVID');
    }
}
