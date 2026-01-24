<?php

namespace App\Domain\Course\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Course Model - Matches `course` table in database.
 * Catalog of courses that can be added to CVs.
 */
class Course extends Model
{
    protected $table = 'course';
    protected $primaryKey = 'CourseID';
    public $timestamps = false;

    protected $fillable = [
        'CourseName',
    ];
}
