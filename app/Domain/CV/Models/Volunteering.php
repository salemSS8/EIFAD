<?php

namespace App\Domain\CV\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Volunteering Model - Matches `volunteering` table in database.
 */
class Volunteering extends Model
{
    protected $table = 'volunteering';
    protected $primaryKey = 'VolunteeringID';
    public $timestamps = false;

    protected $fillable = [
        'CVID',
        'Title',
        'Description',
    ];

    /**
     * Get the CV this volunteering belongs to.
     */
    public function cv(): BelongsTo
    {
        return $this->belongsTo(CV::class, 'CVID', 'CVID');
    }
}
