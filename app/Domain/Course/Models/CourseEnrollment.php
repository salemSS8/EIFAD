<?php

namespace App\Domain\Course\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CourseEnrollment Model - Matches `courseenrollment` table in database.
 */
class CourseEnrollment extends Model
{
    protected $table = 'courseenrollment';
    protected $primaryKey = 'EnrollmentID';
    public $timestamps = false;

    protected $fillable = [
        'CourseAdID',
        'JobSeekerID',
        'EnrolledAt',
        'Status',
    ];

    protected function casts(): array
    {
        return [
            'EnrolledAt' => 'datetime',
        ];
    }

    /**
     * Get the course this enrollment is for.
     */
    public function courseAd(): BelongsTo
    {
        return $this->belongsTo(CourseAd::class, 'CourseAdID', 'CourseAdID');
    }

    /**
     * Get the job seeker who enrolled.
     */
    public function jobSeeker(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\JobSeekerProfile::class, 'JobSeekerID', 'JobSeekerID');
    }
}
