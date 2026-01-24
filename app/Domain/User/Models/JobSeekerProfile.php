<?php

namespace App\Domain\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * JobSeekerProfile Model - Matches `jobseekerprofile` table in database.
 */
class JobSeekerProfile extends Model
{
    protected $table = 'jobseekerprofile';
    protected $primaryKey = 'JobSeekerID';
    public $timestamps = false;

    // Note: JobSeekerID references UserID (1:1 relationship)
    public $incrementing = false;

    protected $fillable = [
        'JobSeekerID',
        'PersonalPhoto',
        'Location',
        'ProfileSummary',
    ];

    /**
     * Get the user that owns this profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'JobSeekerID', 'UserID');
    }

    /**
     * Get all CVs for this job seeker.
     */
    public function cvs(): HasMany
    {
        return $this->hasMany(\App\Domain\CV\Models\CV::class, 'JobSeekerID', 'JobSeekerID');
    }

    /**
     * Get favorite jobs.
     */
    public function favoriteJobs(): HasMany
    {
        return $this->hasMany(\App\Domain\Job\Models\FavoriteJob::class, 'JobSeekerID', 'JobSeekerID');
    }

    /**
     * Get followed companies.
     */
    public function followedCompanies(): HasMany
    {
        return $this->hasMany(\App\Domain\Company\Models\FollowCompany::class, 'JobSeekerID', 'JobSeekerID');
    }

    /**
     * Get job applications.
     */
    public function applications(): HasMany
    {
        return $this->hasMany(\App\Domain\Application\Models\JobApplication::class, 'JobSeekerID', 'JobSeekerID');
    }

    /**
     * Get course enrollments.
     */
    public function courseEnrollments(): HasMany
    {
        return $this->hasMany(\App\Domain\Course\Models\CourseEnrollment::class, 'JobSeekerID', 'JobSeekerID');
    }
}
