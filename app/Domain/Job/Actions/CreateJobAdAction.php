<?php

namespace App\Domain\Job\Actions;

use App\Domain\Job\Models\Job;
use App\Domain\Company\Models\Company;
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
    public function execute(Company $company, int $userId, array $data): Job
    {
        return Job::create([
            'company_id' => $company->id,
            'user_id' => $userId,
            'title' => $data['title'],
            'slug' => Str::slug($data['title']) . '-' . uniqid(),
            'description' => $data['description'],
            'requirements' => $data['requirements'] ?? null,
            'responsibilities' => $data['responsibilities'] ?? null,
            'benefits' => $data['benefits'] ?? null,
            'location' => $data['location'] ?? null,
            'city' => $data['city'] ?? null,
            'country' => $data['country'] ?? null,
            'is_remote' => $data['is_remote'] ?? false,
            'job_type' => $data['job_type'] ?? 'full_time',
            'experience_level' => $data['experience_level'] ?? 'mid',
            'salary_min' => $data['salary_min'] ?? null,
            'salary_max' => $data['salary_max'] ?? null,
            'salary_currency' => $data['salary_currency'] ?? 'USD',
            'is_salary_visible' => $data['is_salary_visible'] ?? true,
            'skills_required' => $data['skills_required'] ?? [],
            'skills_preferred' => $data['skills_preferred'] ?? [],
            'industry' => $data['industry'] ?? null,
            'department' => $data['department'] ?? null,
            'positions_available' => $data['positions_available'] ?? 1,
            'application_deadline' => $data['application_deadline'] ?? null,
            'status' => $data['status'] ?? 'draft',
        ]);
    }
}
