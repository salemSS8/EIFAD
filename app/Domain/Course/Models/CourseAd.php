<?php

namespace App\Domain\Course\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CourseAd Model - Matches `coursead` table in database.
 * Training courses offered by companies.
 */
class CourseAd extends Model
{
    protected $table = 'coursead';
    protected $primaryKey = 'CourseAdID';
    public $timestamps = false;

    protected $fillable = [
        'CompanyID',
        'CourseTitle',
        'Topics',
        'Duration',
        'Location',
        'Trainer',
        'Fees',
        'StartDate',
        'CreatedAt',
        'Status',
    ];

    protected function casts(): array
    {
        return [
            'StartDate' => 'date',
            'CreatedAt' => 'datetime',
            'Fees' => 'integer',
        ];
    }

    /**
     * Get the company offering this course.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Company\Models\CompanyProfile::class, 'CompanyID', 'CompanyID');
    }

    /**
     * Get enrollments for this course.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class, 'CourseAdID', 'CourseAdID');
    }
}
