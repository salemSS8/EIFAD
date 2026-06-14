<?php

namespace Tests\Feature\Jobs;

use App\Domain\Communication\Models\Notification;
use App\Domain\Communication\Models\NotificationSetting;
use App\Domain\Company\Models\CompanyProfile;
use App\Domain\CV\Models\CV;
use App\Domain\Job\Models\JobAd;
use App\Domain\User\Models\Role;
use App\Domain\User\Models\User;
use App\Jobs\NotifyMatchingJobSeekersJob;
use App\Mail\NewJobMatchMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotifyMatchingJobSeekersJobTest extends TestCase
{
    use RefreshDatabase;

    private User $employer;

    private User $jobSeeker;

    private JobAd $jobAd;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['RoleName' => 'Employer']);
        Role::create(['RoleName' => 'JobSeeker']);

        // Create employer with company
        $this->employer = User::factory()->create();
        $this->employer->roles()->attach(Role::where('RoleName', 'Employer')->first());

        CompanyProfile::create([
            'CompanyID' => $this->employer->UserID,
            'CompanyName' => 'شركة تقنية',
        ]);

        // Create job seeker with matching CV
        $this->jobSeeker = User::factory()->create();
        $this->jobSeeker->roles()->attach(Role::where('RoleName', 'JobSeeker')->first());
        DB::table('jobseekerprofile')->insert(['JobSeekerID' => $this->jobSeeker->UserID]);

        CV::create([
            'JobSeekerID' => $this->jobSeeker->UserID,
            'Title' => 'Software Engineer',
            'CreatedAt' => now(),
        ]);

        // Create a job ad
        $this->jobAd = JobAd::create([
            'CompanyID' => $this->employer->UserID,
            'Title' => 'Senior Software Engineer',
            'Description' => 'Looking for an experienced engineer.',
            'PostedAt' => now(),
            'Status' => 'Active',
        ]);
    }

    public function test_job_can_be_dispatched(): void
    {
        Queue::fake();

        NotifyMatchingJobSeekersJob::dispatch($this->jobAd);

        Queue::assertPushed(NotifyMatchingJobSeekersJob::class);
    }

    public function test_creates_notification_for_matching_job_seekers(): void
    {
        Mail::fake();

        $job = new NotifyMatchingJobSeekersJob($this->jobAd);
        $job->handle();

        $this->assertDatabaseHas('notification', [
            'UserID' => $this->jobSeeker->UserID,
            'Type' => 'Job Match',
        ]);
    }

    public function test_sends_email_for_matching_job_seekers(): void
    {
        Mail::fake();

        $job = new NotifyMatchingJobSeekersJob($this->jobAd);
        $job->handle();

        Mail::assertSent(NewJobMatchMail::class, function (NewJobMatchMail $mail) {
            return $mail->hasTo($this->jobSeeker->Email)
                && $mail->jobTitle === 'Senior Software Engineer';
        });
    }

    public function test_does_not_notify_non_matching_job_seekers(): void
    {
        Mail::fake();

        // Create a non-matching job seeker
        $otherSeeker = User::factory()->create();
        $otherSeeker->roles()->attach(Role::where('RoleName', 'JobSeeker')->first());
        DB::table('jobseekerprofile')->insert(['JobSeekerID' => $otherSeeker->UserID]);

        CV::create([
            'JobSeekerID' => $otherSeeker->UserID,
            'Title' => 'Graphic Designer',
            'CreatedAt' => now(),
        ]);

        $job = new NotifyMatchingJobSeekersJob($this->jobAd);
        $job->handle();

        // Should NOT have a notification for the non-matching seeker
        $this->assertDatabaseMissing('notification', [
            'UserID' => $otherSeeker->UserID,
            'Type' => 'Job Match',
        ]);
    }

    public function test_respects_email_disabled_setting(): void
    {
        Mail::fake();

        // Disable email notifications for the job seeker
        NotificationSetting::create([
            'UserID' => $this->jobSeeker->UserID,
            'EmailNotifications' => false,
            'PushNotifications' => true,
            'JobAlerts' => true,
            'ApplicationUpdates' => true,
            'MarketingEmails' => false,
        ]);

        $job = new NotifyMatchingJobSeekersJob($this->jobAd);
        $job->handle();

        // Database notification should still be created
        $this->assertDatabaseHas('notification', [
            'UserID' => $this->jobSeeker->UserID,
            'Type' => 'Job Match',
        ]);

        // But no email should be sent
        Mail::assertNotSent(NewJobMatchMail::class);
    }

    public function test_respects_job_alerts_disabled_setting(): void
    {
        Mail::fake();

        // Disable job alerts for the job seeker
        NotificationSetting::create([
            'UserID' => $this->jobSeeker->UserID,
            'EmailNotifications' => true,
            'PushNotifications' => true,
            'JobAlerts' => false,
            'ApplicationUpdates' => true,
            'MarketingEmails' => false,
        ]);

        $job = new NotifyMatchingJobSeekersJob($this->jobAd);
        $job->handle();

        // Database notification should still be created
        $this->assertDatabaseHas('notification', [
            'UserID' => $this->jobSeeker->UserID,
            'Type' => 'Job Match',
        ]);

        // But no email should be sent
        Mail::assertNotSent(NewJobMatchMail::class);
    }

    public function test_sends_email_by_default_when_no_settings_exist(): void
    {
        Mail::fake();

        // No NotificationSetting record exists — default should be enabled
        $job = new NotifyMatchingJobSeekersJob($this->jobAd);
        $job->handle();

        Mail::assertSent(NewJobMatchMail::class);
    }

    public function test_handles_short_title_words_gracefully(): void
    {
        Mail::fake();

        // Job with title containing only short words (< 3 chars)
        $shortJob = JobAd::create([
            'CompanyID' => $this->employer->UserID,
            'Title' => 'IT QA',
            'Description' => 'Test job',
            'PostedAt' => now(),
            'Status' => 'Active',
        ]);

        $job = new NotifyMatchingJobSeekersJob($shortJob);
        $job->handle();

        // Should not crash, no notifications sent
        Mail::assertNothingSent();
    }
}
