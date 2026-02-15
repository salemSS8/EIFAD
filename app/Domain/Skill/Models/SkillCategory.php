<?php

namespace App\Domain\Skill\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SkillCategory Model - Matches `skillcategory` table in database.
 */
class SkillCategory extends Model
{
    protected $table = 'skillcategory';
    protected $primaryKey = 'CategoryID';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'CategoryID',
        'CategoryName',
    ];

    /**
     * Get skills in this category.
     */
    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class, 'CategoryID', 'CategoryID');
    }
}
