<?php

namespace App\Domain\CV\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CVCourse Model - Matches `cvcourse` table in database.
 */
class CVCourse extends Model
{
    protected $table = 'cvcourse';
    protected $primaryKey = 'CVCourseID';
    public $timestamps = false;

    protected $fillable = [
        'CVID',
        'CourseID',
        'PlaceTaken',
        'DateTaken',
    ];

    protected function casts(): array
    {
        return [
            'DateTaken' => 'date',
        ];
    }

    /**
     * Get the CV this course belongs to.
     */
    public function cv(): BelongsTo
    {
        return $this->belongsTo(CV::class, 'CVID', 'CVID');
    }

    /**
     * Get the course definition.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Course\Models\Course::class, 'CourseID', 'CourseID');
    }
}
