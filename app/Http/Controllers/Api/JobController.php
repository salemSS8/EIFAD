<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Job\Models\JobAd;
use App\Domain\Job\Models\JobSkill;
use App\Domain\Job\Models\FavoriteJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

/**
 * Job Controller - Manages job listings and employer job management.
 */
class JobController extends Controller
{
    /**
     * Search and filter jobs.
     */
    public function index(Request $request): JsonResponse
    {
        $query = JobAd::with(['company:CompanyID,CompanyName,LogoPath', 'skills.skill'])
            ->where('Status', 'Active');

        // Keyword search
        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');
            $query->where(function (Builder $q) use ($keyword) {
                $q->where('Title', 'like', "%{$keyword}%")
                    ->orWhere('Description', 'like', "%{$keyword}%");
            });
        }

        // Location filter
        if ($request->filled('location')) {
            $query->where('Location', 'like', "%{$request->input('location')}%");
        }

        // Work type filter (full_time, part_time, etc.)
        if ($request->filled('work_type')) {
            $query->where('WorkType', $request->input('work_type'));
        }

        // Workplace type filter (remote, onsite, hybrid)
        if ($request->filled('workplace_type')) {
            $query->where('WorkplaceType', $request->input('workplace_type'));
        }

        // Salary range
        if ($request->filled('salary_min')) {
            $query->where('SalaryMax', '>=', $request->input('salary_min'));
        }
        if ($request->filled('salary_max')) {
            $query->where('SalaryMin', '<=', $request->input('salary_max'));
        }

        // Company filter
        if ($request->filled('company_id')) {
            $query->where('CompanyID', $request->input('company_id'));
        }

        // Skill filter
        if ($request->filled('skill_ids')) {
            $skillIds = is_array($request->input('skill_ids'))
                ? $request->input('skill_ids')
                : explode(',', $request->input('skill_ids'));

            $query->whereHas('skills', function ($q) use ($skillIds) {
                $q->whereIn('SkillID', $skillIds);
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'PostedAt');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = min($request->integer('per_page', 15), 50);
        $jobs = $query->paginate($perPage);

        return response()->json($jobs);
    }

    /**
     * Get a single job with details.
     */
    public function show(int $id): JsonResponse
    {
        $job = JobAd::with([
            'company',
            'skills.skill.category',
        ])
            ->where('JobAdID', $id)
            ->firstOrFail();

        return response()->json(['data' => $job]);
    }

    /**
     * Add job to favorites (for job seekers).
     */
    public function addFavorite(Request $request, int $jobId): JsonResponse
    {
        $user = $request->user();
        $profile = $user->jobSeekerProfile;

        if (!$profile) {
            return response()->json(['message' => 'Only job seekers can save jobs'], 403);
        }

        FavoriteJob::firstOrCreate([
            'JobSeekerID' => $profile->JobSeekerID,
            'JobAdID' => $jobId,
        ], [
            'SavedAt' => now(),
        ]);

        return response()->json(['message' => 'Job saved to favorites']);
    }

    /**
     * Remove job from favorites.
     */
    public function removeFavorite(Request $request, int $jobId): JsonResponse
    {
        $user = $request->user();
        $profile = $user->jobSeekerProfile;

        FavoriteJob::where('JobSeekerID', $profile->JobSeekerID)
            ->where('JobAdID', $jobId)
            ->delete();

        return response()->json(['message' => 'Job removed from favorites']);
    }

    /**
     * Get user's favorite jobs.
     */
    public function favorites(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->jobSeekerProfile;

        if (!$profile) {
            return response()->json(['message' => 'Only job seekers can view favorites'], 403);
        }

        $favorites = FavoriteJob::with(['jobAd.company:CompanyID,CompanyName,LogoPath'])
            ->where('JobSeekerID', $profile->JobSeekerID)
            ->orderByDesc('SavedAt')
            ->paginate(15);

        return response()->json($favorites);
    }

    // ==========================================
    // Employer Job Management
    // ==========================================

    /**
     * Get employer's job listings.
     */
    public function employerJobs(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->companyProfile;

        if (!$company) {
            return response()->json(['message' => 'Company profile not found'], 404);
        }

        $jobs = JobAd::withCount('applications')
            ->where('CompanyID', $company->CompanyID)
            ->orderByDesc('PostedAt')
            ->paginate(15);

        return response()->json($jobs);
    }

    /**
     * Create a new job listing.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'responsibilities' => 'nullable|string',
            'requirements' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'workplace_type' => 'nullable|string|max:50',
            'work_type' => 'nullable|string|max:50',
            'salary_min' => 'nullable|integer|min:0',
            'salary_max' => 'nullable|integer|min:0',
            'currency' => 'nullable|string|max:10',
            'skills' => 'nullable|array',
            'skills.*.skill_id' => 'required|exists:skill,SkillID',
            'skills.*.required_level' => 'nullable|string',
            'skills.*.is_mandatory' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $company = $user->companyProfile;

        if (!$company) {
            return response()->json(['message' => 'Company profile not found'], 404);
        }

        $job = JobAd::create([
            'CompanyID' => $company->CompanyID,
            'Title' => $request->input('title'),
            'Description' => $request->input('description'),
            'Responsibilities' => $request->input('responsibilities'),
            'Requirements' => $request->input('requirements'),
            'Location' => $request->input('location'),
            'WorkplaceType' => $request->input('workplace_type'),
            'WorkType' => $request->input('work_type'),
            'SalaryMin' => $request->input('salary_min'),
            'SalaryMax' => $request->input('salary_max'),
            'Currency' => $request->input('currency', 'USD'),
            'PostedAt' => now(),
            'Status' => 'Draft',
        ]);

        // Add skills
        if ($request->filled('skills')) {
            foreach ($request->input('skills') as $skill) {
                JobSkill::create([
                    'JobAdID' => $job->JobAdID,
                    'SkillID' => $skill['skill_id'],
                    'RequiredLevel' => $skill['required_level'] ?? null,
                    'IsMandatory' => $skill['is_mandatory'] ?? false,
                ]);
            }
        }

        return response()->json([
            'message' => 'Job created successfully',
            'data' => $job->load('skills.skill'),
        ], 201);
    }

    /**
     * Update a job listing.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $company = $user->companyProfile;

        $job = JobAd::where('JobAdID', $id)
            ->where('CompanyID', $company->CompanyID)
            ->firstOrFail();

        $job->update($request->only([
            'Title',
            'Description',
            'Responsibilities',
            'Requirements',
            'Location',
            'WorkplaceType',
            'WorkType',
            'SalaryMin',
            'SalaryMax',
            'Currency',
            'Status',
        ]));

        return response()->json([
            'message' => 'Job updated successfully',
            'data' => $job->fresh('skills.skill'),
        ]);
    }

    /**
     * Publish a job (change status to Active).
     */
    public function publish(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $company = $user->companyProfile;

        $job = JobAd::where('JobAdID', $id)
            ->where('CompanyID', $company->CompanyID)
            ->firstOrFail();

        $job->update([
            'Status' => 'Active',
            'PostedAt' => now(),
        ]);

        return response()->json([
            'message' => 'Job published successfully',
            'data' => $job->fresh(),
        ]);
    }

    /**
     * Close a job (change status to Closed).
     */
    public function close(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $company = $user->companyProfile;

        $job = JobAd::where('JobAdID', $id)
            ->where('CompanyID', $company->CompanyID)
            ->firstOrFail();

        $job->update([
            'Status' => 'Closed',
        ]);

        return response()->json([
            'message' => 'Job closed successfully',
            'data' => $job->fresh(),
        ]);
    }

    /**
     * Delete a job listing.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $company = $user->companyProfile;

        $job = JobAd::where('JobAdID', $id)
            ->where('CompanyID', $company->CompanyID)
            ->firstOrFail();

        $job->delete();

        return response()->json(['message' => 'Job deleted successfully']);
    }
}
