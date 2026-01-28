<?php

namespace App\Domain\User\Models;

use App\Domain\Company\Models\CompanyProfile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * User Model - Matches `user` table in database.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\UserFactory::new();
    }

    protected $table = 'user';
    protected $primaryKey = 'UserID';
    public $timestamps = false;

    protected $fillable = [
        'FullName',
        'Email',
        'PasswordHash',
        'Phone',
        'Gender',
        'DateOfBirth',
        'IsVerified',
        'CreatedAt',
        'ProviderID',
        'AuthProvider',
        'Avatar',
        'IsBlocked',
        'BlockedAt',
        'BlockReason',
    ];

    protected $hidden = [
        'PasswordHash',
    ];

    protected function casts(): array
    {
        return [
            'DateOfBirth' => 'date',
            'IsVerified' => 'boolean',
            'CreatedAt' => 'datetime',
        ];
    }

    /**
     * Get the password attribute for authentication.
     */
    public function getAuthPassword()
    {
        return $this->PasswordHash;
    }

    /**
     * Get user's roles (many-to-many through userrole).
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'userrole',
            'UserID',
            'RoleID'
        )->withPivot('AssignedAt');
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('RoleName', $roleName)->exists();
    }

    /**
     * Check if user is a job seeker.
     */
    public function isJobSeeker(): bool
    {
        return $this->hasRole('JobSeeker');
    }

    /**
     * Check if user is an employer.
     */
    public function isEmployer(): bool
    {
        return $this->hasRole('Employer');
    }

    /**
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('Admin');
    }

    /**
     * Get user's job seeker profile.
     */
    public function jobSeekerProfile(): HasOne
    {
        return $this->hasOne(JobSeekerProfile::class, 'JobSeekerID', 'UserID');
    }

    /**
     * Get user's company profile.
     */
    public function companyProfile(): HasOne
    {
        return $this->hasOne(CompanyProfile::class, 'CompanyID', 'UserID');
    }

    /**
     * Get user's notifications.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(\App\Domain\Communication\Models\Notification::class, 'UserID', 'UserID');
    }

    /**
     * Get user's analysis results.
     */
    public function analysisResults(): HasMany
    {
        return $this->hasMany(\App\Domain\AI\Models\AnalysisResult::class, 'UserID', 'UserID');
    }
}
