<?php

namespace App\Http\Controllers\Api;

use App\Domain\Job\Models\FavoriteJob;
use App\Domain\Job\Models\JobAd;
use App\Domain\Job\Models\JobSkill;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Job Controller - Manages job listings and employer job management.
 */
class JobController extends Controller
{
    #[OA\Get(
        path: '/jobs',
        operationId: 'getJobs',
        tags: ['Jobs'],
        summary: 'Search and filter jobs',
        description: 'Public job search with filters for keywords, location, salary, etc.'
    )]
    #[OA\Parameter(name: 'search', in: 'query', description: 'Search keyword in title, description, and requirements', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'location', in: 'query', description: 'Filter by location', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'work_type', in: 'query', description: 'Filter by work type (Full-time, Part-time, Contract, Internship)', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'workplace_type', in: 'query', description: 'Filter by workplace type (Remote, On-site, Hybrid)', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'salary_min', in: 'query', description: 'Minimum salary', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'salary_max', in: 'query', description: 'Maximum salary', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'company_id', in: 'query', description: 'Filter by company ID', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'skill_ids', in: 'query', description: 'Comma-separated skill IDs', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'industry', in: 'query', description: 'Filter by industry (company field of work)', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'sort', in: 'query', description: 'Sort order: latest (default), salary_desc, salary_asc, popular', required: false, schema: new OA\Schema(type: 'string', enum: ['latest', 'salary_desc', 'salary_asc', 'popular']))]
    #[OA\Parameter(name: 'per_page', in: 'query', description: 'Items per page (default 15, max 50)', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'List of jobs',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
            ]
        )
    )]
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search', 'location', 'work_type', 'workplace_type',
            'salary_min', 'salary_max', 'company_id', 'skill_ids',
            'industry', 'sort',
        ]);

        $perPage = min($request->integer('per_page', 15), 50);

        $jobs = (new \App\Domain\Job\Actions\SearchJobsAction)->execute($filters, $perPage);

        return response()->json($jobs);
    }

    /**
     * Get a single job with details.
     */
    #[OA\Get(
        path: '/jobs/{id}',
        operationId: 'getJobDetails',
        tags: ['Jobs'],
        summary: 'Get job details',
        description: 'Returns detailed information about a specific job.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Job details')]
    #[OA\Response(response: 404, description: 'Job not found')]
    public function show(int $id): JsonResponse
    {
        $job = JobAd::with([
            'company',
            'skills.skill.category',
        ])
            ->withCount('applications')
            ->where('JobAdID', $id)
            ->firstOrFail();

        return response()->json(['data' => $job]);
    }

    /**
     * Add job to favorites (for job seekers).
     */
    #[OA\Post(
        path: '/favorites/{jobId}',
        operationId: 'addFavoriteJob',
        tags: ['Jobs', 'Favorites'],
        summary: 'Add job to favorites',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'jobId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Job saved to favorites')]
    public function addFavorite(Request $request, int $jobId): JsonResponse
    {
        $user = $request->user();
        $profile = $user->jobSeekerProfile;

        if (! $profile) {
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
        path: '/favorites/{jobId}',
        operationId: 'removeFavoriteJob',
        tags: ['Jobs', 'Favorites'],
        summary: 'Remove job from favorites',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'jobId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Job removed from favorites')]
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
        path: '/favorites',
        operationId: 'getFavoriteJobs',
        tags: ['Jobs', 'Favorites'],
        summary: 'Get favorite jobs',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Response(response: 200, description: 'List of favorite jobs')]
    public function favorites(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->jobSeekerProfile;

        if (! $profile) {
            return response()->json(['message' => 'Only job seekers can view favorites'], 403);
        }

        $favorites = FavoriteJob::with(['jobAd.company:CompanyID,CompanyName,LogoPath'])
            ->where('JobSeekerID', $profile->JobSeekerID)
            ->orderByDesc('SavedAt')
            ->paginate(15);

        return response()->json($favorites);
    }

    /**
     * Get matching jobs for the current user based on their CV.
     */
    #[OA\Get(
        path: '/jobs/matching',
        operationId: 'getMatchingJobs',
        tags: ['Jobs'],
        summary: 'Get matching jobs',
        description: 'Returns jobs that match the active or specified CV of the job seeker.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'cv_id', in: 'query', description: 'CV ID for matching. If missing, uses the latest CV.', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'min_score', in: 'query', description: 'Minimum match score (default 50)', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'List of matching jobs')]
    public function matching(Request $request): JsonResponse
    {
        $user = $request->user();
        $jobSeekerProfile = $user->jobSeekerProfile;

        if (! $jobSeekerProfile) {
            return response()->json(['message' => 'Only job seekers can access matching jobs'], 403);
        }

        $cvId = $request->input('cv_id');
        $minScore = $request->input('min_score', 50);

        if (! $cvId) {
            $latestCv = \App\Domain\CV\Models\CV::where('JobSeekerID', $jobSeekerProfile->JobSeekerID)
                ->orderByDesc('CreatedAt')
                ->first();

            if (! $latestCv) {
                return response()->json(['message' => 'No CV found for matching'], 404);
            }
            $cvId = $latestCv->CVID;
        } else {
            // Verify CV ownership
            \App\Domain\CV\Models\CV::where('CVID', $cvId)
                ->where('JobSeekerID', $jobSeekerProfile->JobSeekerID)
                ->firstOrFail();
        }

        $matches = \App\Domain\AI\Models\CVJobMatch::with(['jobAd.company'])
            ->where('CVID', $cvId)
            ->where('MatchScore', '>=', $minScore)
            ->whereHas('jobAd', function ($query) {
                $query->where('Status', 'Active');
            })
            ->orderByDesc('MatchScore')
            ->paginate(15);

        // Extract jobs dynamically and inject match score
        $matches->getCollection()->transform(function ($match) {
            $job = $match->jobAd;
            $job->match_score = $match->MatchScore;

            return $job;
        });

        return response()->json($matches);
    }

    // ==========================================
    // Employer Job Management
    // ==========================================

    /**
     * Get employer's job listings.
     */
    #[OA\Get(
        path: '/employer/jobs',
        operationId: 'getEmployerJobs',
        tags: ['Employer', 'Jobs'],
        summary: "Get employer's jobs",
        security: [['bearerAuth' => []]]
    )]
    #[OA\Response(response: 200, description: "List of employer's jobs")]
    public function employerJobs(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->companyProfile;

        if (! $company) {
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
        path: '/employer/jobs',
        operationId: 'createJob',
        tags: ['Employer', 'Jobs'],
        summary: 'Create a new job',
        security: [['bearerAuth' => []]]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['title', 'description'],
            properties: [
                new OA\Property(property: 'title', type: 'string', example: 'Software Engineer'),
                new OA\Property(property: 'description', type: 'string'),
                new OA\Property(property: 'responsibilities', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'requirements', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'benefits', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'location', type: 'string'),
                new OA\Property(property: 'work_type', type: 'string', enum: ['Full-time', 'Part-time', 'Contract']),
                new OA\Property(property: 'workplace_type', type: 'string', enum: ['Remote', 'On-site', 'Hybrid']),
                new OA\Property(property: 'salary_min', type: 'integer'),
                new OA\Property(property: 'salary_max', type: 'integer'),
                new OA\Property(property: 'expiry_date', type: 'string', format: 'date-time', example: '2024-12-31 23:59:59'),
                new OA\Property(property: 'skills', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'skill_id', type: 'integer'),
                        new OA\Property(property: 'required_level', type: 'string'),
                        new OA\Property(property: 'is_mandatory', type: 'boolean'),
                    ]
                )),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Job created successfully')]
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'responsibilities' => 'nullable|array',
            'responsibilities.*' => 'string',
            'requirements' => 'nullable|array',
            'requirements.*' => 'string',
            'benefits' => 'nullable|array',
            'benefits.*' => 'string',
            'location' => 'nullable|string|max:255',
            'workplace_type' => 'nullable|string|max:50',
            'work_type' => 'nullable|string|max:50',
            'salary_min' => 'nullable|integer|min:0',
            'salary_max' => 'nullable|integer|min:0',
            'currency' => 'nullable|string|max:10',
            'expiry_date' => 'nullable|date',
            'skills' => 'nullable|array',
            'skills.*.skill_id' => 'required|exists:skill,SkillID',
            'skills.*.required_level' => 'nullable|string',
            'skills.*.is_mandatory' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $company = $user->companyProfile;

        if (! $company) {
            return response()->json(['message' => 'Company profile not found'], 404);
        }

        $job = JobAd::create([
            'CompanyID' => $company->CompanyID,
            'Title' => $request->input('title'),
            'Description' => $request->input('description'),
            'Responsibilities' => $request->input('responsibilities'),
            'Requirements' => $request->input('requirements'),
            'Benefits' => $request->input('benefits'),
            'Location' => $request->input('location'),
            'WorkplaceType' => $request->input('workplace_type'),
            'WorkType' => $request->input('work_type'),
            'SalaryMin' => $request->input('salary_min'),
            'SalaryMax' => $request->input('salary_max'),
            'Currency' => $request->input('currency', 'USD'),
            'PostedAt' => now(),
            'ExpiryDate' => $request->input('expiry_date'),
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
        path: '/employer/jobs/{id}',
        operationId: 'updateJob',
        tags: ['Employer', 'Jobs'],
        summary: 'Update job details',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'title', type: 'string'),
                new OA\Property(property: 'description', type: 'string'),
                new OA\Property(property: 'responsibilities', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'requirements', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'benefits', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'location', type: 'string'),
                new OA\Property(property: 'workplace_type', type: 'string', enum: ['Remote', 'On-site', 'Hybrid']),
                new OA\Property(property: 'work_type', type: 'string', enum: ['Full-time', 'Part-time', 'Contract']),
                new OA\Property(property: 'salary_min', type: 'integer'),
                new OA\Property(property: 'salary_max', type: 'integer'),
                new OA\Property(property: 'currency', type: 'string'),
                new OA\Property(property: 'expiry_date', type: 'string', format: 'date-time', example: '2024-12-31 23:59:59'),
                new OA\Property(property: 'status', type: 'string', enum: ['Draft', 'Active', 'Closed']),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Job updated successfully')]
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $company = $user->companyProfile;

        $job = JobAd::where('JobAdID', $id)
            ->where('CompanyID', $company->CompanyID)
            ->firstOrFail();

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'responsibilities' => 'nullable|array',
            'responsibilities.*' => 'string',
            'requirements' => 'nullable|array',
            'requirements.*' => 'string',
            'benefits' => 'nullable|array',
            'benefits.*' => 'string',
            'location' => 'nullable|string|max:255',
            'workplace_type' => 'nullable|string|max:50',
            'work_type' => 'nullable|string|max:50',
            'salary_min' => 'nullable|integer|min:0',
            'salary_max' => 'nullable|integer|min:0',
            'currency' => 'nullable|string|max:10',
            'expiry_date' => 'nullable|date',
            'status' => 'sometimes|string|in:Draft,Active,Closed',
        ]);

        $job->update([
            'Title' => $request->input('title', $job->Title),
            'Description' => $request->input('description', $job->Description),
            'Responsibilities' => $request->has('responsibilities') ? $request->input('responsibilities') : $job->Responsibilities,
            'Requirements' => $request->has('requirements') ? $request->input('requirements') : $job->Requirements,
            'Benefits' => $request->has('benefits') ? $request->input('benefits') : $job->Benefits,
            'Location' => $request->input('location', $job->Location),
            'WorkplaceType' => $request->input('workplace_type', $job->WorkplaceType),
            'WorkType' => $request->input('work_type', $job->WorkType),
            'SalaryMin' => $request->input('salary_min', $job->SalaryMin),
            'SalaryMax' => $request->input('salary_max', $job->SalaryMax),
            'Currency' => $request->input('currency', $job->Currency),
            'ExpiryDate' => $request->input('expiry_date', $job->ExpiryDate),
            'Status' => $request->input('status', $job->Status),
        ]);

        return response()->json([
            'message' => 'Job updated successfully',
            'data' => $job->fresh('skills.skill'),
        ]);
    }

    /**
     * Publish a job (change status to Active).
     */
    #[OA\Post(
        path: '/employer/jobs/{id}/publish',
        operationId: 'publishJob',
        tags: ['Employer', 'Jobs'],
        summary: 'Publish a job',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Job published')]
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
        path: '/employer/jobs/{id}/close',
        operationId: 'closeJob',
        tags: ['Employer', 'Jobs'],
        summary: 'Close a job',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Job closed')]
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
        path: '/employer/jobs/{id}',
        operationId: 'deleteJob',
        tags: ['Employer', 'Jobs'],
        summary: 'Delete a job',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Job deleted')]
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
