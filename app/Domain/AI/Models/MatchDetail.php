<?php

namespace App\Domain\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MatchDetail Model - Matches `matchdetail` table in database.
 * Stores skill-level breakdown of a CV-Job match.
 */
class MatchDetail extends Model
{
    protected $table = 'matchdetail';
    protected $primaryKey = 'MatchDetailID';
    public $timestamps = false;

    protected $fillable = [
        'MatchID',
        'SkillID',
        'CVLevel',
        'RequiredLevel',
        'IsMatched',
    ];

    protected function casts(): array
    {
        return [
            'IsMatched' => 'boolean',
        ];
    }

    /**
     * Get the match this detail belongs to.
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(CVJobMatch::class, 'MatchID', 'MatchID');
    }

    /**
     * Get the skill being compared.
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Skill\Models\Skill::class, 'SkillID', 'SkillID');
    }
}
