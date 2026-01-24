<?php

namespace App\Domain\Application\Actions;

use App\Domain\Application\Models\Application;
use App\Domain\Application\Jobs\EvaluateCandidateJob;
use App\Domain\Job\Models\Job;
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
    public function execute(User $user, Job $job, ?int $cvId = null, ?string $coverLetter = null): Application
    {
        // Validate eligibility
        $this->validateEligibility($user, $job);

        return DB::transaction(function () use ($user, $job, $cvId, $coverLetter) {
            // Use active CV if none specified
            if (!$cvId) {
                $activeCV = $user->activeCV;
                $cvId = $activeCV?->id;
            }

            // Create application
            $application = Application::create([
                'user_id' => $user->id,
                'job_id' => $job->id,
                'cv_id' => $cvId,
                'cover_letter' => $coverLetter,
                'status' => 'pending',
                'applied_at' => now(),
            ]);

            // Update job application count
            $job->increment('applications_count');

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
    private function validateEligibility(User $user, Job $job): void
    {
        // Must be a job seeker
        if (!$user->isJobSeeker()) {
            throw BusinessRuleException::because('Only job seekers can apply to jobs');
        }

        // Job must be published
        if ($job->status !== 'published') {
            throw BusinessRuleException::because('This job is not accepting applications');
        }

        // Check deadline
        if ($job->application_deadline && $job->application_deadline->isPast()) {
            throw BusinessRuleException::because('Application deadline has passed');
        }

        // Check if already applied
        $existingApplication = Application::where('user_id', $user->id)
            ->where('job_id', $job->id)
            ->exists();

        if ($existingApplication) {
            throw BusinessRuleException::because('You have already applied to this job');
        }
    }
}
