<?php

namespace App\Domain\CV\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Education Model - Matches `education` table in database.
 */
class Education extends Model
{
    protected $table = 'education';
    protected $primaryKey = 'EducationID';
    public $timestamps = false;

    protected $fillable = [
        'CVID',
        'Institution',
        'DegreeName',
        'Major',
        'GraduationYear',
    ];

    protected function casts(): array
    {
        return [
            'GraduationYear' => 'integer',
        ];
    }

    /**
     * Get the CV this education belongs to.
     */
    public function cv(): BelongsTo
    {
        return $this->belongsTo(CV::class, 'CVID', 'CVID');
    }
}
