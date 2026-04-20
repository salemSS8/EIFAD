<?php

namespace App\Domain\Company\Actions;

use App\Domain\Company\Models\CompanyProfile;
use App\Domain\Shared\Contracts\ActionInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Use case: Search and filter companies by name, location, and field of work.
 */
class SearchCompaniesAction implements ActionInterface
{
    /**
     * Execute the search companies use case.
     *
     * @param  array<string, mixed>  $filters
     */
    public function execute(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = CompanyProfile::query()
            ->where('IsCompanyVerified', true);

        $this->applyNameFilter($query, $filters);
        $this->applyLocationFilter($query, $filters);
        $this->applyFieldFilter($query, $filters);

        // Sort by name by default for listings
        $query->orderBy('CompanyName');

        return $query->paginate($perPage);
    }

    /**
     * Filter by company name.
     */
    protected function applyNameFilter(Builder $query, array $filters): void
    {
        if (empty($filters['name'])) {
            return;
        }

        $query->where('CompanyName', 'like', "%{$filters['name']}%");
    }

    /**
     * Filter by location (Address).
     */
    protected function applyLocationFilter(Builder $query, array $filters): void
    {
        if (empty($filters['location'])) {
            return;
        }

        $query->where('Address', 'like', "%{$filters['location']}%");
    }

    /**
     * Filter by field of work.
     */
    protected function applyFieldFilter(Builder $query, array $filters): void
    {
        if (empty($filters['field'])) {
            return;
        }

        $query->where('FieldOfWork', 'like', "%{$filters['field']}%");
    }
}
