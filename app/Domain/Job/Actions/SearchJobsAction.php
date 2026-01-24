<?php

namespace App\Domain\Job\Actions;

use App\Domain\Job\Models\Job;
use App\Domain\Shared\Contracts\ActionInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Use case: Search and filter jobs.
 */
class SearchJobsAction implements ActionInterface
{
    /**
     * Execute the search jobs use case.
     */
    public function execute(array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Job::query()
            ->with(['company:id,name,logo,slug'])
            ->where('status', 'published')
            ->whereNotNull('published_at');

        // Search by keyword
        if (!empty($filters['keyword'])) {
            $query->where(function (Builder $q) use ($filters) {
                $q->where('title', 'like', "%{$filters['keyword']}%")
                    ->orWhere('description', 'like', "%{$filters['keyword']}%");
            });
        }

        // Filter by location
        if (!empty($filters['location'])) {
            $query->where(function (Builder $q) use ($filters) {
                $q->where('location', 'like', "%{$filters['location']}%")
                    ->orWhere('city', 'like', "%{$filters['location']}%")
                    ->orWhere('country', 'like', "%{$filters['location']}%");
            });
        }

        // Filter by remote
        if (isset($filters['is_remote'])) {
            $query->where('is_remote', $filters['is_remote']);
        }

        // Filter by job type
        if (!empty($filters['job_type'])) {
            $types = is_array($filters['job_type']) ? $filters['job_type'] : [$filters['job_type']];
            $query->whereIn('job_type', $types);
        }

        // Filter by experience level
        if (!empty($filters['experience_level'])) {
            $levels = is_array($filters['experience_level']) ? $filters['experience_level'] : [$filters['experience_level']];
            $query->whereIn('experience_level', $levels);
        }

        // Filter by salary range
        if (!empty($filters['salary_min'])) {
            $query->where('salary_max', '>=', $filters['salary_min']);
        }
        if (!empty($filters['salary_max'])) {
            $query->where('salary_min', '<=', $filters['salary_max']);
        }

        // Filter by skills
        if (!empty($filters['skills'])) {
            $skills = is_array($filters['skills']) ? $filters['skills'] : [$filters['skills']];
            foreach ($skills as $skill) {
                $query->whereJsonContains('skills_required', $skill);
            }
        }

        // Filter by company
        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        // Filter by industry
        if (!empty($filters['industry'])) {
            $query->where('industry', $filters['industry']);
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'published_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }
}
