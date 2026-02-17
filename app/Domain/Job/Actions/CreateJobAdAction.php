<?php

namespace App\Domain\Job\Actions;

use App\Domain\Job\Models\JobAd;
use App\Domain\Company\Models\CompanyProfile;
use App\Domain\Shared\Contracts\ActionInterface;
use Illuminate\Support\Str;

/**
 * Use case: Create a new job advertisement.
 */
class CreateJobAdAction implements ActionInterface
{
    /**
     * Execute the create job ad use case.
     */
    public function execute(CompanyProfile $company, int $userId, array $data): JobAd
    {
        return JobAd::create([
            'CompanyID' => $company->CompanyID ?? $company->id ?? $userId, // Assuming CompanyID is available or using ID
            // 'user_id' => $userId, // Not in JobAd schema
            'Title' => $data['title'],
            // 'slug' => Str::slug($data['title']) . '-' . uniqid(), // Not in JobAd schema
            'Description' => $data['description'],
            'Requirements' => $data['requirements'] ?? null,
            'Responsibilities' => $data['responsibilities'] ?? null,
            // 'benefits' => $data['benefits'] ?? null, // Not in JobAd schema
            'Location' => $data['location'] ?? ($data['city'] . ', ' . $data['country']), // Combine if location not set? Or just use location.
            // 'city' => $data['city'] ?? null, // Not in JobAd schema
            // 'country' => $data['country'] ?? null, // Not in JobAd schema
            'WorkplaceType' => ($data['is_remote'] ?? false) ? 'Remote' : 'On-site', // Map is_remote
            'WorkType' => $data['job_type'] ?? 'full_time',
            // 'experience_level' => $data['experience_level'] ?? 'mid', // Not in JobAd schema
            'SalaryMin' => $data['salary_min'] ?? null,
            'SalaryMax' => $data['salary_max'] ?? null,
            'Currency' => $data['salary_currency'] ?? 'USD',
            // 'is_salary_visible' => $data['is_salary_visible'] ?? true, // Not in JobAd schema
            // 'skills_required' => $data['skills_required'] ?? [], // Not in JobAd schema (relational)
            // 'skills_preferred' => $data['skills_preferred'] ?? [], // Not in JobAd schema
            // 'industry' => $data['industry'] ?? null, // Not in JobAd schema
            // 'department' => $data['department'] ?? null, // Not in JobAd schema
            // 'positions_available' => $data['positions_available'] ?? 1, // Not in JobAd schema
            // 'application_deadline' => $data['application_deadline'] ?? null, // Not in JobAd schema (PostedAt only)
            'Status' => $data['status'] ?? 'draft',
            'PostedAt' => ($data['status'] ?? 'draft') === 'published' ? now() : null, // Set PostedAt if published
        ]);
    }
}
