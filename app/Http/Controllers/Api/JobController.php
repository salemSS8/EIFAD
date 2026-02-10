<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Job\Models\JobAd;
use App\Domain\Job\Models\JobSkill;
use App\Domain\Job\Models\FavoriteJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

use OpenApi\Attributes as OA;

/**
 * Job Controller - Manages job listings and employer job management.
 */
class JobController extends Controller
{
    /**
     * Search and filter jobs.
     */
    #[OA\Get(
        path: "/jobs",
        operationId: "getJobs",
        tags: ["Jobs"],
        summary: "Search and filter jobs",
        description: "Public job search with filters for keywords, location, salary, etc."
    )]
    #[OA\Parameter(name: "keyword", in: "query", description: "Search keyword for title or description", required: false, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "location", in: "query", description: "Filter by location", required: false, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "work_type", in: "query", description: "Filter by work type (Full-time, Part-time)", required: false, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "workplace_type", in: "query", description: "Filter by workplace type (Remote, On-site, Hybrid)", required: false, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "salary_min", in: "query", description: "Minimum salary", required: false, schema: new OA\Schema(type: "integer"))]
    #[OA\Parameter(name: "salary_max", in: "query", description: "Maximum salary", required: false, schema: new OA\Schema(type: "integer"))]
    #[OA\Parameter(name: "company_id", in: "query", description: "Filter by company ID", required: false, schema: new OA\Schema(type: "integer"))]
    #[OA\Parameter(name: "skill_ids", in: "query", description: "Comma-separated skill IDs", required: false, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "per_page", in: "query", description: "Items per page (default 15)", required: false, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(
        response: 200,
        description: "List of jobs",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object"))
            ]
        )
    )]
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
    #[OA\Get(
        path: "/jobs/{id}",
        operationId: "getJobDetails",
        tags: ["Jobs"],
        summary: "Get job details",
        description: "Returns detailed information about a specific job."
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "Job details")]
    #[OA\Response(response: 404, description: "Job not found")]
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
    #[OA\Post(
        path: "/favorites/{jobId}",
        operationId: "addFavoriteJob",
        tags: ["Jobs", "Favorites"],
        summary: "Add job to favorites",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "jobId", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "Job saved to favorites")]
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
    #[OA\Delete(
        path: "/favorites/{jobId}",
        operationId: "removeFavoriteJob",
        tags: ["Jobs", "Favorites"],
        summary: "Remove job from favorites",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "jobId", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "Job removed from favorites")]
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
    #[OA\Get(
        path: "/favorites",
        operationId: "getFavoriteJobs",
        tags: ["Jobs", "Favorites"],
        summary: "Get favorite jobs",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Response(response: 200, description: "List of favorite jobs")]
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
    #[OA\Get(
        path: "/employer/jobs",
        operationId: "getEmployerJobs",
        tags: ["Employer", "Jobs"],
        summary: "Get employer's jobs",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Response(response: 200, description: "List of employer's jobs")]
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
    #[OA\Post(
        path: "/employer/jobs",
        operationId: "createJob",
        tags: ["Employer", "Jobs"],
        summary: "Create a new job",
        security: [["bearerAuth" => []]]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["title", "description"],
            properties: [
                new OA\Property(property: "title", type: "string", example: "Software Engineer"),
                new OA\Property(property: "description", type: "string"),
                new OA\Property(property: "responsibilities", type: "string"),
                new OA\Property(property: "requirements", type: "string"),
                new OA\Property(property: "location", type: "string"),
                new OA\Property(property: "work_type", type: "string", enum: ["Full-time", "Part-time", "Contract"]),
                new OA\Property(property: "workplace_type", type: "string", enum: ["Remote", "On-site", "Hybrid"]),
                new OA\Property(property: "salary_min", type: "integer"),
                new OA\Property(property: "salary_max", type: "integer"),
                new OA\Property(property: "skills", type: "array", items: new OA\Items(
                    properties: [
                        new OA\Property(property: "skill_id", type: "integer"),
                        new OA\Property(property: "required_level", type: "string"),
                        new OA\Property(property: "is_mandatory", type: "boolean"),
                    ]
                )),
            ]
        )
    )]
    #[OA\Response(response: 201, description: "Job created successfully")]
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
    #[OA\Put(
        path: "/employer/jobs/{id}",
        operationId: "updateJob",
        tags: ["Employer", "Jobs"],
        summary: "Update job details",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "title", type: "string"),
                new OA\Property(property: "description", type: "string"),
                new OA\Property(property: "status", type: "string", enum: ["Draft", "Active", "Closed"]),
            ]
        )
    )]
    #[OA\Response(response: 200, description: "Job updated successfully")]
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
    #[OA\Post(
        path: "/employer/jobs/{id}/publish",
        operationId: "publishJob",
        tags: ["Employer", "Jobs"],
        summary: "Publish a job",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "Job published")]
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
    #[OA\Post(
        path: "/employer/jobs/{id}/close",
        operationId: "closeJob",
        tags: ["Employer", "Jobs"],
        summary: "Close a job",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "Job closed")]
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
    #[OA\Delete(
        path: "/employer/jobs/{id}",
        operationId: "deleteJob",
        tags: ["Employer", "Jobs"],
        summary: "Delete a job",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "Job deleted")]
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
