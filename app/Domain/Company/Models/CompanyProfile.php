<?php

namespace App\Domain\Company\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * CompanyProfile Model - Matches `companyprofile` table in database.
 */
class CompanyProfile extends Model
{
    protected $table = 'companyprofile';
    protected $primaryKey = 'CompanyID';
    public $timestamps = false;

    // Note: CompanyID references UserID (1:1 relationship)
    public $incrementing = false;

    protected $fillable = [
        'CompanyID',
        'CompanyName',
        'OrganizationName',
        'Address',
        'Description',
        'LogoPath',
        'WebsiteURL',
        'EstablishedYear',
        'EmployeeCount',
        'FieldOfWork',
        'IsCompanyVerified',
    ];

    protected function casts(): array
    {
        return [
            'IsCompanyVerified' => 'boolean',
            'EstablishedYear' => 'integer',
            'EmployeeCount' => 'integer',
        ];
    }

    /**
     * Get the user that owns this company profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'CompanyID', 'UserID');
    }

    /**
     * Get all job ads posted by this company.
     */
    public function jobAds(): HasMany
    {
        return $this->hasMany(\App\Domain\Job\Models\JobAd::class, 'CompanyID', 'CompanyID');
    }

    /**
     * Get all course ads offered by this company.
     */
    public function courseAds(): HasMany
    {
        return $this->hasMany(\App\Domain\Course\Models\CourseAd::class, 'CompanyID', 'CompanyID');
    }

    /**
     * Get company specializations.
     */
    public function specializations(): BelongsToMany
    {
        return $this->belongsToMany(
            CompanySpecialization::class,
            'companyprofilespecialization',
            'CompanyID',
            'SpecID'
        );
    }

    /**
     * Get followers (job seekers following this company).
     */
    public function followers(): HasMany
    {
        return $this->hasMany(FollowCompany::class, 'CompanyID', 'CompanyID');
    }
}
