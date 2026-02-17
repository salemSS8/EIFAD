<?php

namespace App\Domain\Application\Actions;

use App\Domain\Application\Models\JobApplication;
use App\Domain\Application\Jobs\EvaluateCandidateJob;
use App\Domain\Job\Models\JobAd;
use App\Domain\User\Models\User;
use App\Domain\Shared\Contracts\ActionInterface;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\DB;

/**
 * Use case: Apply to a job.
 */
class ApplyToJobAction implements ActionInterface
{
    /**
     * Execute the apply to job use case.
     */
    public function execute(User $user, JobAd $job, ?int $cvId = null, ?string $coverLetter = null): JobApplication
    {
        // Validate eligibility
        $this->validateEligibility($user, $job);

        return DB::transaction(function () use ($user, $job, $cvId, $coverLetter) {
            // Use active CV if none specified
            if (!$cvId) {
                // Get latest CV from JobSeekerProfile
                $jobSeeker = $user->jobSeekerProfile;
                $activeCV = $jobSeeker?->cvs()->latest('UpdatedAt')->first();
                $cvId = $activeCV?->CVID;
            }

            // Create application
            $application = JobApplication::create([
                'JobSeekerID' => $user->UserID ?? $user->id, // Map UserID
                'JobAdID' => $job->JobAdID,
                'CVID' => $cvId,
                'Notes' => $coverLetter, // Map cover_letter to Notes as it's the only text field available
                'Status' => 'pending',
                'AppliedAt' => now(),
            ]);

            // Update job application count
            // $job->increment('applications_count'); // Column applications_count MISSING in JobAd schema. Ignored.

            // Dispatch async evaluation job
            if ($cvId) {
                EvaluateCandidateJob::dispatch($application);
            }

            return $application;
        });
    }

    /**
     * Validate if user is eligible to apply.
     */
    private function validateEligibility(User $user, JobAd $job): void
    {
        // Must be a job seeker
        if (!$user->isJobSeeker()) {
            throw BusinessRuleException::because('Only job seekers can apply to jobs');
        }

        // Job must be published
        if ($job->Status !== 'published') {
            throw BusinessRuleException::because('This job is not accepting applications');
        }

        // Check deadline
        // JobAd does not have application_deadline column. Ignoring check.
        // if ($job->application_deadline && $job->application_deadline->isPast()) {
        //    throw BusinessRuleException::because('Application deadline has passed');
        // }

        // Check if already applied
        $existingApplication = JobApplication::where('JobSeekerID', $user->UserID ?? $user->id)
            ->where('JobAdID', $job->JobAdID)
            ->exists();

        if ($existingApplication) {
            throw BusinessRuleException::because('You have already applied to this job');
        }
    }
}
