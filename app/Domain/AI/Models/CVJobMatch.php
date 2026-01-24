<?php

namespace App\Domain\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CVJobMatch Model - Matches `cvjobmatch` table in database.
 * 
 * Supports both:
 * - Rule-based matching (no AI)
 * - AI-generated explanations (Gemini)
 */
class CVJobMatch extends Model
{
    protected $table = 'cvjobmatch';
    protected $primaryKey = 'MatchID';
    public $timestamps = false;

    protected $fillable = [
        'CVID',
        'JobAdID',
        'MatchScore',
        'MatchDate',

        // Rule-Based Compatibility (NO AI)
        'CompatibilityLevel',  // HIGH | MEDIUM | LOW
        'SkillsScore',
        'ExperienceScore',
        'EducationScore',
        'ScoreBreakdown',
        'ScoringMethod',
        'CalculatedAt',

        // AI Explanations (Gemini - TEXT ONLY)
        'Explanation',
        'Strengths',
        'Gaps',
        'AIModel',
        'ExplainedAt',
    ];

    protected function casts(): array
    {
        return [
            'MatchDate' => 'datetime',
            'MatchScore' => 'integer',
            'SkillsScore' => 'integer',
            'ExperienceScore' => 'integer',
            'EducationScore' => 'integer',
            'ScoreBreakdown' => 'array',
            'Strengths' => 'array',
            'Gaps' => 'array',
            'CalculatedAt' => 'datetime',
            'ExplainedAt' => 'datetime',
        ];
    }

    /**
     * Get the CV in this match.
     */
    public function cv(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\CV\Models\CV::class, 'CVID', 'CVID');
    }

    /**
     * Get the job ad in this match.
     */
    public function jobAd(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Job\Models\JobAd::class, 'JobAdID', 'JobAdID');
    }

    /**
     * Get detailed match breakdown by skill.
     */
    public function details(): HasMany
    {
        return $this->hasMany(MatchDetail::class, 'MatchID', 'MatchID');
    }
}
