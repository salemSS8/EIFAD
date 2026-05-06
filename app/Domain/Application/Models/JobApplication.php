<?php

namespace App\Domain\Application\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * JobApplication Model - Matches `jobapplication` table in database.
 */
class JobApplication extends Model
{
    protected $table = 'jobapplication';

    protected $primaryKey = 'ApplicationID';

    public $timestamps = false;

    protected $hidden = [
        // 'CV',
    ];

    protected $fillable = [
        'JobAdID',
        'JobSeekerID',
        'CVID',
        'CV',
        'JobSeekerName',
        'JobSeekerEmail',
        'JobSeekerPhone',
        'JobSeekerAddress',
        'AppliedAt',
        'Status',
        'MatchScore',
        'AboutMe',
        'Notes',
    ];

    protected $appends = [
        'cv_url',
    ];

    protected function casts(): array
    {
        return [
            'AppliedAt' => 'datetime',
            'MatchScore' => 'integer',
        ];
    }

    /**
     * Get the job ad being applied for.
     */
    public function jobAd(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Job\Models\JobAd::class, 'JobAdID', 'JobAdID');
    }

    /**
     * Get the job seeker who applied.
     */
    public function jobSeeker(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\JobSeekerProfile::class, 'JobSeekerID', 'JobSeekerID');
    }

    /**
     * Get the CV used in this application.
     */
    public function cv(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\CV\Models\CV::class, 'CVID', 'CVID');
    }

    /**
     * Get all AI match analyses for this application's CV.
     */
    public function cvJobMatches(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domain\AI\Models\CVJobMatch::class, 'CVID', 'CVID');
    }

    /**
     * Get the full URL for the uploaded CV file.
     */
    public function getCvUrlAttribute(): ?string
    {
        if ($this->CV) {
            return asset('storage/'.$this->CV);
        }

        return null;
    }
}
