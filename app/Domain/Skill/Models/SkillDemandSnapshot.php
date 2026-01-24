<?php

namespace App\Domain\Skill\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SkillDemandSnapshot Model - Matches `skilldemandsnapshot` table in database.
 */
class SkillDemandSnapshot extends Model
{
    protected $table = 'skilldemandsnapshot';
    protected $primaryKey = 'SnapshotID';
    public $timestamps = false;

    protected $fillable = [
        'SkillID',
        'DemandCount',
        'SnapshotDate',
    ];

    protected function casts(): array
    {
        return [
            'SnapshotDate' => 'date',
            'DemandCount' => 'integer',
        ];
    }

    /**
     * Get the skill this snapshot is for.
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'SkillID', 'SkillID');
    }
}
