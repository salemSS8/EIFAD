<?php

namespace App\Domain\Skill\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Skill Model - Matches `skill` table in database.
 */
class Skill extends Model
{
    protected $table = 'skill';
    protected $primaryKey = 'SkillID';
    public $timestamps = false;

    protected $fillable = [
        'SkillName',
        'CategoryID',
    ];

    /**
     * Get the category this skill belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(SkillCategory::class, 'CategoryID', 'CategoryID');
    }

    /**
     * Get CV skills using this skill.
     */
    public function cvSkills(): HasMany
    {
        return $this->hasMany(\App\Domain\CV\Models\CVSkill::class, 'SkillID', 'SkillID');
    }

    /**
     * Get job skills requiring this skill.
     */
    public function jobSkills(): HasMany
    {
        return $this->hasMany(\App\Domain\Job\Models\JobSkill::class, 'SkillID', 'SkillID');
    }

    /**
     * Get demand snapshots for this skill.
     */
    public function demandSnapshots(): HasMany
    {
        return $this->hasMany(SkillDemandSnapshot::class, 'SkillID', 'SkillID');
    }
}
