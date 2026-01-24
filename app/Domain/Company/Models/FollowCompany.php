<?php

namespace App\Domain\Company\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FollowCompany Model - Matches `followcompany` table in database.
 */
class FollowCompany extends Model
{
    protected $table = 'followcompany';
    protected $primaryKey = 'FollowID';
    public $timestamps = false;

    protected $fillable = [
        'JobSeekerID',
        'CompanyID',
        'FollowedAt',
    ];

    protected function casts(): array
    {
        return [
            'FollowedAt' => 'datetime',
        ];
    }

    /**
     * Get the job seeker following.
     */
    public function jobSeeker(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\JobSeekerProfile::class, 'JobSeekerID', 'JobSeekerID');
    }

    /**
     * Get the followed company.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class, 'CompanyID', 'CompanyID');
    }
}
