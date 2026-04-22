<?php

namespace App\Http\Controllers\Api;

use App\Domain\AI\Models\CVJobMatch;
use App\Domain\Application\Models\JobApplication;
use App\Domain\CV\Models\CV;
use App\Domain\Job\Models\JobAd;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        path: '/applications',
        operationId: 'getMyApplications',
        tags: ['Applications'],
        summary: 'Get my applications',
        description: 'Returns a list of applications submitted by the current job seeker.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Response(response: 200, description: 'List of applications')]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->jobSeekerProfile;

        if (! $profile) {
            return response()->json(['message' => 'Only job seekers can view applications'], 403);
        }

        $applications = JobApplication::with(['jobAd.company:CompanyID,CompanyName,LogoPath', 'cv:CVID,Title'])
            ->where('JobSeekerID', $profile->JobSeekerID)
            ->where('Status', '!=', 'withdrawn')
            ->orderByDesc('AppliedAt')
            ->paginate(15);

        return response()->json($applications);
    }

    /**
     * Get a specific application.
     */
    #[OA\Get(
        path: '/applications/{id}',
        operationId: 'getApplication',
        tags: ['Applications'],
        summary: 'Get application details',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Application details')]
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
        path: '/applications',
        operationId: 'submitApplication',
        tags: ['Applications'],
        summary: 'Submit a job application',
        security: [['bearerAuth' => []]]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['job_id'],
                properties: [
                    new OA\Property(property: 'job_id', type: 'integer', description: 'ID of the job to apply for'),
                    new OA\Property(property: 'cv_id', type: 'integer', description: 'ID of an existing internal CV (required if cv file is not provided)'),
                    new OA\Property(property: 'cv', type: 'string', format: 'binary', description: 'PDF resume file (required if cv_id is not provided)'),
                    new OA\Property(property: 'JobSeekerName', type: 'string', description: 'Override full name for this application'),
                    new OA\Property(property: 'JobSeekerEmail', type: 'string', format: 'email', description: 'Override email for this application'),
                    new OA\Property(property: 'JobSeekerPhone', type: 'string', description: 'Override phone for this application'),
                    new OA\Property(property: 'JobSeekerAddress', type: 'string', description: 'Override address for this application'),
                    new OA\Property(property: 'AboutMe', type: 'string', description: 'Short candidate summary'),
                    new OA\Property(property: 'notes', type: 'string', description: 'Additional notes for the employer'),
                ]
            )
        )
    )]
    #[OA\Response(response: 201, description: 'Application submitted successfully')]
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'job_id' => 'required|exists:jobad,JobAdID',
            'cv_id' => 'required_without:cv|exists:cv,CVID',
            'cv' => 'required_without:cv_id|file|mimes:pdf|max:5120',
            'JobSeekerName' => 'nullable|string|max:255',
            'JobSeekerEmail' => 'nullable|string|email|max:255',
            'JobSeekerPhone' => 'nullable|string|max:20',
            'JobSeekerAddress' => 'nullable|string|max:500',
            'AboutMe' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        $profile = $user->jobSeekerProfile;

        if (! $profile) {
            return response()->json(['message' => __('application.only_job_seekers_can_apply')], 403);
        }

        $cvFile = $request->file('cv');
        $cvId = $request->input('cv_id');

        // Check if job is active
        $job = JobAd::where('JobAdID', $request->input('job_id'))
            ->where('Status', 'Active')
            ->first();

        if (! $job) {
            return response()->json(['message' => __('application.job_not_active')], 422);
        }

        // Check if job is expired
        if ($job->ExpiryDate && $job->ExpiryDate->isPast()) {
            return response()->json(['message' => 'The application deadline for this job has passed'], 422);
        }

        // Check for duplicate application
        $existingApplication = JobApplication::where('JobSeekerID', $profile->JobSeekerID)
            ->where('JobAdID', $request->input('job_id'))
            ->exists();

        if ($existingApplication) {
            return response()->json(['message' => __('application.application_already_exists')], 422);
        }

        $matchScore = null;
        $cvPath = null;

        if ($cvId) {
            // Verify CV belongs to user
            $cv = CV::where('CVID', $cvId)
                ->where('JobSeekerID', $profile->JobSeekerID)
                ->first();

            if (! $cv) {
                return response()->json(['message' => __('application.cv_not_found')], 422);
            }

            // Get match score if available (only for internal CVs)
            $matchScore = CVJobMatch::where('CVID', $cv->CVID)
                ->where('JobAdID', $job->JobAdID)
                ->value('MatchScore');
        }

        if ($cvFile) {
            $cvPath = $cvFile->storeAs('cvs', $profile->JobSeekerID . '_' . time() . '_' . $cvFile->getClientOriginalName(), 'public');
            // If user chose to upload a PDF file, do not calculate the match score.
            $matchScore = null;
        }

        $application = JobApplication::create([
            'JobAdID' => $job->JobAdID,
            'JobSeekerID' => $profile->JobSeekerID,
            'CVID' => $cvId,
            'CV' => $cvPath,
            'JobSeekerName' => $request->input('JobSeekerName') ?? $user->FullName ?? $user->Name,
            'JobSeekerEmail' => $request->input('JobSeekerEmail') ?? $user->Email,
            'JobSeekerPhone' => $request->input('JobSeekerPhone') ?? $user->Phone,
            'JobSeekerAddress' => $request->input('JobSeekerAddress') ?? $user->Address,
            'AppliedAt' => now(),
            'Status' => __('application.Pending'),
            'MatchScore' => $matchScore,
            'AboutMe' => $request->input('AboutMe'),
            'Notes' => $request->input('notes'),
        ]);

        // Create Notification for the Employer
        $notification = \App\Domain\Communication\Models\Notification::create([
            'UserID' => $job->CompanyID, // CompanyID acts as UserID for employer
            'Type' => 'New Application',
            'Content' => "A new candidate applied for your job: {$job->Title}",
            'IsRead' => false,
            'CreatedAt' => now(),
        ]);

        broadcast(new \App\Events\NotificationReceived($notification))->toOthers();

        // Dispatch AI Applicant Screener
        \App\Jobs\ProcessApplicationScreener::dispatch($application);

        return response()->json([
            'message' => __('application.application_submitted'),
            'data' => $application->load(['jobAd', 'cv']),
        ], 201);
    }

    /**
     * Auto apply to best matching job.
     */
    #[OA\Post(
        path: '/applications/auto-apply',
        operationId: 'autoApply',
        tags: ['Applications'],
        summary: 'Auto apply to a matching job',
        description: 'Finds the best matching active job for the provided CV and applies automatically.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['cv_id'],
            properties: [
                new OA\Property(property: 'cv_id', type: 'integer'),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Auto application result')]
    public function autoApply(Request $request): JsonResponse
    {
        $request->validate([
            'cv_id' => 'required|exists:cv,CVID',
        ]);

        $user = $request->user();
        $profile = $user->jobSeekerProfile;

        if (! $profile) {
            return response()->json(['message' => 'Only job seekers can use auto apply'], 403);
        }

        // Verify CV belongs to user
        $cv = CV::where('CVID', $request->input('cv_id'))
            ->where('JobSeekerID', $profile->JobSeekerID)
            ->first();

        if (! $cv) {
            return response()->json(['message' => 'Invalid CV specified'], 422);
        }

        // Get already applied jobs
        $appliedJobIds = JobApplication::where('JobSeekerID', $profile->JobSeekerID)
            ->pluck('JobAdID')
            ->toArray();

        // Find the highest match score job which is still active and not applied to
        $bestMatch = CVJobMatch::where('CVID', $cv->CVID)
            ->whereNotIn('JobAdID', $appliedJobIds)
            ->whereHas('jobAd', function ($q) {
                $q->where('Status', 'Active')
                    ->where(function ($q2) {
                        $q2->whereNull('ExpiryDate')->orWhere('ExpiryDate', '>', now());
                    });
            })
            ->orderByDesc('MatchScore')
            ->first();

        if (! $bestMatch || $bestMatch->MatchScore < 70) {
            return response()->json(['message' => 'No highly matching jobs found at this time. (Match score must be at least 70%)'], 404);
        }

        $job = $bestMatch->jobAd;

        $application = JobApplication::create([
            'JobAdID' => $job->JobAdID,
            'JobSeekerID' => $profile->JobSeekerID,
            'CVID' => $cv->CVID,
            'AppliedAt' => now(),
            'Status' => 'Pending',
            'MatchScore' => $bestMatch->MatchScore,
            'Notes' => 'Auto-applied via AI Match System',
        ]);

        // Create Notification for the Employer
        $notification = \App\Domain\Communication\Models\Notification::create([
            'UserID' => $job->CompanyID,
            'Type' => 'New Application',
            'Content' => "A new candidate auto-applied for your job: {$job->Title}",
            'IsRead' => false,
            'CreatedAt' => now(),
        ]);

        broadcast(new \App\Events\NotificationReceived($notification))->toOthers();

        // Dispatch AI Applicant Screener
        \App\Jobs\ProcessApplicationScreener::dispatch($application);

        return response()->json([
            'message' => 'Successfully auto-applied to a highly matching job',
            'data' => $application->load(['jobAd', 'cv']),
        ], 201);
    }

    /**
     * Withdraw an application.
     */
    #[OA\Post(
        path: '/applications/{id}/withdraw',
        operationId: 'withdrawApplication',
        tags: ['Applications'],
        summary: 'Withdraw an application',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Application withdrawn')]
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
        path: '/employer/jobs/{jobId}/applications',
        operationId: 'getJobApplications',
        tags: ['Employer', 'Applications'],
        summary: 'Get applications for a job',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'jobId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'List of applications for the job')]
    public function jobApplications(Request $request, int $jobId): JsonResponse
    {
        $user = $request->user();
        $company = $user->companyProfile;

        if (! $company) {
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
        path: '/employer/applications/{id}/status',
        operationId: 'updateApplicationStatus',
        tags: ['Employer', 'Applications'],
        summary: 'Update application status',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['status'],
            properties: [
                new OA\Property(property: 'status', type: 'string', enum: ['Pending', 'Reviewed', 'Shortlisted', 'Interviewing', 'Offered', 'Hired', 'Rejected']),
                new OA\Property(property: 'notes', type: 'string'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Status updated')]
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:Pending,Reviewed,Shortlisted,Interviewing,Offered,Hired,Rejected',
            'notes' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        $company = $user->companyProfile;

        if (! $company) {
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

        $application->load('jobAd:JobAdID,Title');

        // Notify the Job Seeker about the status change
        $notification = \App\Domain\Communication\Models\Notification::create([
            'UserID' => $application->JobSeekerID, // JobSeekerID acts as UserID
            'Type' => 'Application Update',
            'Content' => "The status of your application for '{$application->jobAd->Title}' has been updated to {$application->Status}.",
            'IsRead' => false,
            'CreatedAt' => now(),
        ]);

        broadcast(new \App\Events\NotificationReceived($notification))->toOthers();

        return response()->json([
            'message' => 'Application status updated',
            'data' => $application->fresh(),
        ]);
    }
}
