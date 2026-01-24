<?php

namespace App\Domain\CV\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CV Model - Matches `cv` table in database.
 */
class CV extends Model
{
    protected $table = 'cv';
    protected $primaryKey = 'CVID';
    public $timestamps = false;

    protected $fillable = [
        'JobSeekerID',
        'Title',
        'PersonalSummary',
        'CreatedAt',
        'UpdatedAt',
    ];

    protected function casts(): array
    {
        return [
            'CreatedAt' => 'datetime',
            'UpdatedAt' => 'datetime',
        ];
    }

    /**
     * Get the job seeker that owns this CV.
     */
    public function jobSeeker(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\JobSeekerProfile::class, 'JobSeekerID', 'JobSeekerID');
    }

    /**
     * Get CV skills.
     */
    public function skills(): HasMany
    {
        return $this->hasMany(CVSkill::class, 'CVID', 'CVID');
    }

    /**
     * Get CV languages.
     */
    public function languages(): HasMany
    {
        return $this->hasMany(CVLanguage::class, 'CVID', 'CVID');
    }

    /**
     * Get CV courses.
     */
    public function courses(): HasMany
    {
        return $this->hasMany(CVCourse::class, 'CVID', 'CVID');
    }

    /**
     * Get education entries.
     */
    public function education(): HasMany
    {
        return $this->hasMany(Education::class, 'CVID', 'CVID');
    }

    /**
     * Get experience entries.
     */
    public function experiences(): HasMany
    {
        return $this->hasMany(Experience::class, 'CVID', 'CVID');
    }

    /**
     * Get volunteering entries.
     */
    public function volunteering(): HasMany
    {
        return $this->hasMany(Volunteering::class, 'CVID', 'CVID');
    }

    /**
     * Get job matches for this CV.
     */
    public function jobMatches(): HasMany
    {
        return $this->hasMany(\App\Domain\AI\Models\CVJobMatch::class, 'CVID', 'CVID');
    }

    /**
     * Get skill gap analyses.
     */
    public function skillGapAnalyses(): HasMany
    {
        return $this->hasMany(\App\Domain\AI\Models\SkillGapAnalysis::class, 'CVID', 'CVID');
    }

    /**
     * Get applications using this CV.
     */
    public function applications(): HasMany
    {
        return $this->hasMany(\App\Domain\Application\Models\JobApplication::class, 'CVID', 'CVID');
    }
}
