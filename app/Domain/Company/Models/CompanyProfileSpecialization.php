<?php

namespace App\Domain\Company\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CompanyProfileSpecialization Model - Matches `companyprofilespecialization` table.
 * Pivot model for company-specialization many-to-many relationship.
 */
class CompanyProfileSpecialization extends Model
{
    protected $table = 'companyprofilespecialization';
    protected $primaryKey = 'CompanySpecID';
    public $timestamps = false;

    protected $fillable = [
        'CompanyID',
        'SpecID',
    ];

    /**
     * Get the company profile.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class, 'CompanyID', 'CompanyID');
    }

    /**
     * Get the specialization.
     */
    public function specialization(): BelongsTo
    {
        return $this->belongsTo(CompanySpecialization::class, 'SpecID', 'SpecID');
    }
}
