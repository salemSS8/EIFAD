<?php

namespace App\Domain\Job\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FavoriteJob Model - Matches `favoritejob` table in database.
 */
class FavoriteJob extends Model
{
    protected $table = 'favoritejob';
    protected $primaryKey = 'FavoriteID';
    public $timestamps = false;

    protected $fillable = [
        'JobSeekerID',
        'JobAdID',
        'SavedAt',
    ];

    protected function casts(): array
    {
        return [
            'SavedAt' => 'datetime',
        ];
    }

    /**
     * Get the job seeker who favorited this job.
     */
    public function jobSeeker(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\JobSeekerProfile::class, 'JobSeekerID', 'JobSeekerID');
    }

    /**
     * Get the favorited job ad.
     */
    public function jobAd(): BelongsTo
    {
        return $this->belongsTo(JobAd::class, 'JobAdID', 'JobAdID');
    }
}
