<?php

namespace App\Domain\CV\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CVSkill Model - Matches `cvskill` table in database.
 */
class CVSkill extends Model
{
    protected $table = 'cvskill';
    protected $primaryKey = 'CVSkillID';
    public $timestamps = false;

    protected $fillable = [
        'CVID',
        'SkillID',
        'SkillLevel',
    ];

    /**
     * Get the CV this skill belongs to.
     */
    public function cv(): BelongsTo
    {
        return $this->belongsTo(CV::class, 'CVID', 'CVID');
    }

    /**
     * Get the skill definition.
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Skill\Models\Skill::class, 'SkillID', 'SkillID');
    }
}
