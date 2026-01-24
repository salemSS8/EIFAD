<?php

namespace App\Domain\CV\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Experience Model - Matches `experience` table in database.
 */
class Experience extends Model
{
    protected $table = 'experience';
    protected $primaryKey = 'ExperienceID';
    public $timestamps = false;

    protected $fillable = [
        'CVID',
        'JobTitle',
        'CompanyName',
        'StartDate',
        'EndDate',
        'Responsibilities',
    ];

    protected function casts(): array
    {
        return [
            'StartDate' => 'date',
            'EndDate' => 'date',
        ];
    }

    /**
     * Get the CV this experience belongs to.
     */
    public function cv(): BelongsTo
    {
        return $this->belongsTo(CV::class, 'CVID', 'CVID');
    }
}
