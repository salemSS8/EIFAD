<?php

namespace App\Domain\Job\Actions;

use App\Domain\Job\Models\JobAd;
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
        $query = JobAd::query()
            ->with(['company:CompanyID,CompanyName,LogoPath,OrganizationName']) // adapted columns
            ->where('Status', 'published')
            ->whereNotNull('PostedAt');

        // Search by keyword
        if (!empty($filters['keyword'])) {
            $query->where(function (Builder $q) use ($filters) {
                $q->where('Title', 'like', "%{$filters['keyword']}%")
                    ->orWhere('Description', 'like', "%{$filters['keyword']}%");
            });
        }

        // Filter by location
        if (!empty($filters['location'])) {
            $query->where(function (Builder $q) use ($filters) {
                $q->where('Location', 'like', "%{$filters['location']}%");
                // City and country columns do not exist in JobAd table
                //    ->orWhere('city', 'like', "%{$filters['location']}%")
                //    ->orWhere('country', 'like', "%{$filters['location']}%");
            });
        }

        // Filter by remote
        if (isset($filters['is_remote'])) {
            // Mapping is_remote (bool) to WorkplaceType (string)
            $workplaceType = $filters['is_remote'] ? 'Remote' : 'On-site'; // Assumption
            $query->where('WorkplaceType', $workplaceType);
        }

        // Filter by job type
        if (!empty($filters['job_type'])) {
            $types = is_array($filters['job_type']) ? $filters['job_type'] : [$filters['job_type']];
            $query->whereIn('WorkType', $types);
        }

        // Filter by experience level
        if (!empty($filters['experience_level'])) {
            $levels = is_array($filters['experience_level']) ? $filters['experience_level'] : [$filters['experience_level']];
            // Experience level column missing in JobAd
            // $query->whereIn('experience_level', $levels);
        }

        // Filter by salary range
        if (!empty($filters['salary_min'])) {
            $query->where('SalaryMax', '>=', $filters['salary_min']);
        }
        if (!empty($filters['salary_max'])) {
            $query->where('SalaryMin', '<=', $filters['salary_max']);
        }

        // Filter by skills
        if (!empty($filters['skills'])) {
            $skills = is_array($filters['skills']) ? $filters['skills'] : [$filters['skills']];
            // Skills are in related table, not JSON column.
            // foreach ($skills as $skill) {
            //    $query->whereJsonContains('skills_required', $skill);
            // }
            $query->whereHas('skills', function ($q) use ($skills) {
                $q->whereIn('SkillID', $skills); // Assuming filter passes SkillIDs
            });
        }

        // Filter by company
        if (!empty($filters['company_id'])) {
            $query->where('CompanyID', $filters['company_id']);
        }

        // Filter by industry
        if (!empty($filters['industry'])) {
            // Industry column missing in JobAd (present in CompanyProfile)
            $query->whereHas('company', function ($q) use ($filters) {
                $q->where('FieldOfWork', $filters['industry']);
            });
        }

        // Sorting
        $sortBy = match ($filters['sort_by'] ?? 'published_at') {
            'published_at' => 'PostedAt',
            'created_at' => 'PostedAt',
            default => 'PostedAt'
        };
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }
}
