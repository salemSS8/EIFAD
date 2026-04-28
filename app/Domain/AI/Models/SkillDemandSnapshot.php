<?php

namespace App\Domain\AI\Models;

use App\Domain\Skill\Models\Skill;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillDemandSnapshot extends Model
{
    protected $table = 'skilldemandsnapshot';

    protected $primaryKey = 'SnapshotID';

    public $timestamps = false;

    protected $fillable = [
        'SkillID',
        'industry_id',
        'city_name',
        'DemandCount',
        'SnapshotDate',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'DemandCount' => 'integer',
            'SnapshotDate' => 'date',
        ];
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'SkillID', 'SkillID');
    }
}
