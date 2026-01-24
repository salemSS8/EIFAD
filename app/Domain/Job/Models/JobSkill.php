<?php

namespace App\Domain\Job\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * JobSkill Model - Matches `jobskill` table in database.
 */
class JobSkill extends Model
{
    protected $table = 'jobskill';
    protected $primaryKey = 'JobSkillID';
    public $timestamps = false;

    protected $fillable = [
        'JobAdID',
        'SkillID',
        'RequiredLevel',
        'ImportanceWeight',
        'IsMandatory',
    ];

    protected function casts(): array
    {
        return [
            'ImportanceWeight' => 'integer',
            'IsMandatory' => 'boolean',
        ];
    }

    /**
     * Get the job ad this skill requirement belongs to.
     */
    public function jobAd(): BelongsTo
    {
        return $this->belongsTo(JobAd::class, 'JobAdID', 'JobAdID');
    }

    /**
     * Get the skill definition.
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Skill\Models\Skill::class, 'SkillID', 'SkillID');
    }
}
