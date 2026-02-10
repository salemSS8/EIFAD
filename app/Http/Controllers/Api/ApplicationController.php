<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Application\Models\JobApplication;
use App\Domain\Job\Models\JobAd;
use App\Domain\CV\Models\CV;
use App\Domain\AI\Models\CVJobMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use OpenApi\Attributes as OA;

/**
 * Application Controller - Manages job applications.
 */
class ApplicationController extends Controller
{
    /**
     * Get current user's applications (for job seekers).
     */
    #[OA\Get(
        path: "/applications",
        operationId: "getMyApplications",
        tags: ["Applications"],
        summary: "Get my applications",
        description: "Returns a list of applications submitted by the current job seeker.",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Response(response: 200, description: "List of applications")]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->jobSeekerProfile;

        if (!$profile) {
            return response()->json(['message' => 'Only job seekers can view applications'], 403);
        }

        $applications = JobApplication::with(['jobAd.company:CompanyID,CompanyName,LogoPath', 'cv:CVID,Title'])
            ->where('JobSeekerID', $profile->JobSeekerID)
            ->orderByDesc('AppliedAt')
            ->paginate(15);

        return response()->json($applications);
    }

    /**
     * Get a specific application.
     */
    #[OA\Get(
        path: "/applications/{id}",
        operationId: "getApplication",
        tags: ["Applications"],
        summary: "Get application details",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "Application details")]
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $profile = $user->jobSeekerProfile;

        $application = JobApplication::with(['jobAd.company', 'jobAd.skills.skill', 'cv'])
            ->where('ApplicationID', $id)
            ->where('JobSeekerID', $profile->JobSeekerID)
            ->firstOrFail();

        return response()->json(['data' => $application]);
    }

    /**
     * Apply to a job.
     */
    #[OA\Post(
        path: "/applications",
        operationId: "submitApplication",
        tags: ["Applications"],
        summary: "Submit a job application",
        security: [["bearerAuth" => []]]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["job_id", "cv_id"],
            properties: [
                new OA\Property(property: "job_id", type: "integer"),
                new OA\Property(property: "cv_id", type: "integer"),
                new OA\Property(property: "notes", type: "string"),
            ]
        )
    )]
    #[OA\Response(response: 201, description: "Application submitted successfully")]
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'job_id' => 'required|exists:jobad,JobAdID',
            'cv_id' => 'required|exists:cv,CVID',
            'notes' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        $profile = $user->jobSeekerProfile;

        if (!$profile) {
            return response()->json(['message' => 'Only job seekers can apply to jobs'], 403);
        }

        // Verify CV belongs to user
        $cv = CV::where('CVID', $request->input('cv_id'))
            ->where('JobSeekerID', $profile->JobSeekerID)
            ->first();

        if (!$cv) {
            return response()->json(['message' => 'Invalid CV specified'], 422);
        }

        // Check if job is active
        $job = JobAd::where('JobAdID', $request->input('job_id'))
            ->where('Status', 'Active')
            ->first();

        if (!$job) {
            return response()->json(['message' => 'This job is not accepting applications'], 422);
        }

        // Check for duplicate application
        $existingApplication = JobApplication::where('JobSeekerID', $profile->JobSeekerID)
            ->where('JobAdID', $request->input('job_id'))
            ->exists();

        if ($existingApplication) {
            return response()->json(['message' => 'You have already applied to this job'], 422);
        }

        // Get match score if available
        $matchScore = CVJobMatch::where('CVID', $cv->CVID)
            ->where('JobAdID', $job->JobAdID)
            ->value('MatchScore');

        $application = JobApplication::create([
            'JobAdID' => $job->JobAdID,
            'JobSeekerID' => $profile->JobSeekerID,
            'CVID' => $cv->CVID,
            'AppliedAt' => now(),
            'Status' => 'Pending',
            'MatchScore' => $matchScore,
            'Notes' => $request->input('notes'),
        ]);

        return response()->json([
            'message' => 'Application submitted successfully',
            'data' => $application->load(['jobAd', 'cv']),
        ], 201);
    }

    /**
     * Withdraw an application.
     */
    #[OA\Post(
        path: "/applications/{id}/withdraw",
        operationId: "withdrawApplication",
        tags: ["Applications"],
        summary: "Withdraw an application",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "Application withdrawn")]
    public function withdraw(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $profile = $user->jobSeekerProfile;

        $application = JobApplication::where('ApplicationID', $id)
            ->where('JobSeekerID', $profile->JobSeekerID)
            ->firstOrFail();

        if ($application->Status === 'Withdrawn') {
            return response()->json(['message' => 'Application already withdrawn'], 422);
        }

        $application->update(['Status' => 'Withdrawn']);

        return response()->json(['message' => 'Application withdrawn successfully']);
    }

    // ==========================================
    // Employer Application Management
    // ==========================================

    /**
     * Get applications for a job (for employers).
     */
    #[OA\Get(
        path: "/employer/jobs/{jobId}/applications",
        operationId: "getJobApplications",
        tags: ["Employer", "Applications"],
        summary: "Get applications for a job",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "jobId", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "List of applications for the job")]
    public function jobApplications(Request $request, int $jobId): JsonResponse
    {
        $user = $request->user();
        $company = $user->companyProfile;

        if (!$company) {
            return response()->json(['message' => 'Company profile not found'], 404);
        }

        // Verify job belongs to company
        $job = JobAd::where('JobAdID', $jobId)
            ->where('CompanyID', $company->CompanyID)
            ->firstOrFail();

        $applications = JobApplication::with([
            'jobSeeker.user:UserID,FullName,Email',
            'cv.skills.skill',
            'cv.experiences',
        ])
            ->where('JobAdID', $jobId)
            ->orderByDesc('MatchScore')
            ->orderByDesc('AppliedAt')
            ->paginate(15);

        return response()->json($applications);
    }

    /**
     * Update application status (for employers).
     */
    #[OA\Put(
        path: "/employer/applications/{id}/status",
        operationId: "updateApplicationStatus",
        tags: ["Employer", "Applications"],
        summary: "Update application status",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["status"],
            properties: [
                new OA\Property(property: "status", type: "string", enum: ["Pending", "Reviewed", "Shortlisted", "Interviewing", "Offered", "Hired", "Rejected"]),
                new OA\Property(property: "notes", type: "string"),
            ]
        )
    )]
    #[OA\Response(response: 200, description: "Status updated")]
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:Pending,Reviewed,Shortlisted,Interviewing,Offered,Hired,Rejected',
            'notes' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        $company = $user->companyProfile;

        if (!$company) {
            return response()->json(['message' => 'Company profile not found'], 404);
        }

        // Verify application is for a job owned by this company
        $application = JobApplication::whereHas('jobAd', function ($q) use ($company) {
            $q->where('CompanyID', $company->CompanyID);
        })->where('ApplicationID', $id)->firstOrFail();

        $application->update([
            'Status' => $request->input('status'),
            'Notes' => $request->input('notes', $application->Notes),
        ]);

        return response()->json([
            'message' => 'Application status updated',
            'data' => $application->fresh(),
        ]);
    }
}
