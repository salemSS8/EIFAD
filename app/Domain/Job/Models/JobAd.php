<?php

namespace App\Domain\Job\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * JobAd Model - Matches `jobad` table in database.
 */
class JobAd extends Model
{
    protected $table = 'jobad';
    protected $primaryKey = 'JobAdID';
    public $timestamps = false;

    protected $fillable = [
        'CompanyID',
        'Title',
        'Description',
        'Responsibilities',
        'Requirements',
        'Location',
        'WorkplaceType',
        'WorkType',
        'SalaryMin',
        'SalaryMax',
        'Currency',
        'PostedAt',
        'Status',
    ];

    protected function casts(): array
    {
        return [
            'PostedAt' => 'datetime',
            'SalaryMin' => 'integer',
            'SalaryMax' => 'integer',
        ];
    }

    /**
     * Get the company that posted this job.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Company\Models\CompanyProfile::class, 'CompanyID', 'CompanyID');
    }

    /**
     * Get required skills for this job.
     */
    public function skills(): HasMany
    {
        return $this->hasMany(JobSkill::class, 'JobAdID', 'JobAdID');
    }

    /**
     * Get applications for this job.
     */
    public function applications(): HasMany
    {
        return $this->hasMany(\App\Domain\Application\Models\JobApplication::class, 'JobAdID', 'JobAdID');
    }

    /**
     * Get users who favorited this job.
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(FavoriteJob::class, 'JobAdID', 'JobAdID');
    }

    /**
     * Get CV matches for this job.
     */
    public function cvMatches(): HasMany
    {
        return $this->hasMany(\App\Domain\AI\Models\CVJobMatch::class, 'JobAdID', 'JobAdID');
    }

    /**
     * Get skill gap analyses for this job.
     */
    public function skillGapAnalyses(): HasMany
    {
        return $this->hasMany(\App\Domain\AI\Models\SkillGapAnalysis::class, 'JobAdID', 'JobAdID');
    }
}
