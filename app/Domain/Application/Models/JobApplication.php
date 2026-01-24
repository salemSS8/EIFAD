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

    protected $fillable = [
        'JobAdID',
        'JobSeekerID',
        'CVID',
        'AppliedAt',
        'Status',
        'MatchScore',
        'Notes',
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
}
