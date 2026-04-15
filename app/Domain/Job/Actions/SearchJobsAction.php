<?php

namespace App\Domain\Job\Actions;

use App\Domain\Job\Models\JobAd;
use App\Domain\Shared\Contracts\ActionInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Use case: Search and filter jobs with advanced filtering and sorting.
 */
class SearchJobsAction implements ActionInterface
{
    /**
     * Execute the search jobs use case.
     *
     * @param  array<string, mixed>  $filters
     */
    public function execute(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = JobAd::query()
            ->with([
                'company:CompanyID,CompanyName,LogoPath,OrganizationName,FieldOfWork',
                'skills.skill',
            ])
            ->withCount('applications')
            ->where('Status', 'Active');

        $this->applyKeywordFilter($query, $filters);
        $this->applyLocationFilter($query, $filters);
        $this->applyWorkTypeFilter($query, $filters);
        $this->applyWorkplaceTypeFilter($query, $filters);
        $this->applySalaryFilter($query, $filters);
        $this->applySkillsFilter($query, $filters);
        $this->applyCompanyFilter($query, $filters);
        $this->applyIndustryFilter($query, $filters);
        $this->applySorting($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * Search by keyword in Title and Description.
     */
    protected function applyKeywordFilter(Builder $query, array $filters): void
    {
        if (empty($filters['search'])) {
            return;
        }

        $keyword = $filters['search'];
        $query->where(function (Builder $q) use ($keyword) {
            $q->where('Title', 'like', "%{$keyword}%")
                ->orWhere('Description', 'like', "%{$keyword}%")
                ->orWhere('Requirements', 'like', "%{$keyword}%");
        });
    }

    /**
     * Filter by location.
     */
    protected function applyLocationFilter(Builder $query, array $filters): void
    {
        if (empty($filters['location'])) {
            return;
        }

        $query->where('Location', 'like', "%{$filters['location']}%");
    }

    /**
     * Filter by work type (Full-time, Part-time, Contract, Internship).
     */
    protected function applyWorkTypeFilter(Builder $query, array $filters): void
    {
        if (empty($filters['work_type'])) {
            return;
        }

        $types = is_array($filters['work_type']) ? $filters['work_type'] : [$filters['work_type']];
        $query->whereIn('WorkType', $types);
    }

    /**
     * Filter by workplace type (Remote, On-site, Hybrid).
     */
    protected function applyWorkplaceTypeFilter(Builder $query, array $filters): void
    {
        if (empty($filters['workplace_type'])) {
            return;
        }

        $query->where('WorkplaceType', $filters['workplace_type']);
    }

    /**
     * Filter by salary range.
     */
    protected function applySalaryFilter(Builder $query, array $filters): void
    {
        if (! empty($filters['salary_min'])) {
            $query->where('SalaryMax', '>=', (int) $filters['salary_min']);
        }

        if (! empty($filters['salary_max'])) {
            $query->where('SalaryMin', '<=', (int) $filters['salary_max']);
        }
    }

    /**
     * Filter by required skills (SkillIDs).
     */
    protected function applySkillsFilter(Builder $query, array $filters): void
    {
        if (empty($filters['skill_ids'])) {
            return;
        }

        $skillIds = is_array($filters['skill_ids'])
            ? $filters['skill_ids']
            : explode(',', $filters['skill_ids']);

        $query->whereHas('skills', function (Builder $q) use ($skillIds) {
            $q->whereIn('SkillID', $skillIds);
        });
    }

    /**
     * Filter by company ID.
     */
    protected function applyCompanyFilter(Builder $query, array $filters): void
    {
        if (empty($filters['company_id'])) {
            return;
        }

        $query->where('CompanyID', (int) $filters['company_id']);
    }

    /**
     * Filter by industry (via company's FieldOfWork).
     */
    protected function applyIndustryFilter(Builder $query, array $filters): void
    {
        if (empty($filters['industry'])) {
            return;
        }

        $query->whereHas('company', function (Builder $q) use ($filters) {
            $q->where('FieldOfWork', $filters['industry']);
        });
    }

    /**
     * Apply sorting: latest (default), salary_desc, salary_asc.
     */
    protected function applySorting(Builder $query, array $filters): void
    {
        $sort = $filters['sort'] ?? 'latest';

        match ($sort) {
            'salary_desc' => $query->orderByDesc('SalaryMax'),
            'salary_asc' => $query->orderBy('SalaryMin'),
            'popular' => $query->orderByDesc('applications_count'),
            default => $query->orderByDesc('PostedAt'),
        };
    }
}
