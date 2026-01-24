<?php

namespace App\Domain\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SkillGapAnalysis Model - Matches `skillgapanalysis` table in database.
 * Stores skill gaps between a CV and a Job.
 */
class SkillGapAnalysis extends Model
{
    protected $table = 'skillgapanalysis';
    protected $primaryKey = 'GapID';
    public $timestamps = false;

    protected $fillable = [
        'CVID',
        'JobAdID',
        'SkillID',
        'CVLevel',
        'RequiredLevel',
        'GapType',
    ];

    /**
     * Get the CV this gap analysis is for.
     */
    public function cv(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\CV\Models\CV::class, 'CVID', 'CVID');
    }

    /**
     * Get the job ad this gap analysis is for.
     */
    public function jobAd(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Job\Models\JobAd::class, 'JobAdID', 'JobAdID');
    }

    /**
     * Get the skill with the gap.
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Skill\Models\Skill::class, 'SkillID', 'SkillID');
    }
}
