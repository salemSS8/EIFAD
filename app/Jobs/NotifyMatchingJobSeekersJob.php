<?php

namespace App\Jobs;

use App\Domain\Communication\Models\Notification;
use App\Domain\Communication\Models\NotificationSetting;
use App\Domain\CV\Models\CV;
use App\Domain\Job\Models\JobAd;
use App\Domain\User\Models\User;
use App\Events\NotificationReceived;
use App\Mail\NewJobMatchMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifyMatchingJobSeekersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public JobAd $jobAd) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $jobAd = $this->jobAd->load('company');
        $companyName = $jobAd->company?->CompanyName ?? 'شركة';

        // Split job title into keywords for LIKE matching
        $titleWords = array_filter(explode(' ', $jobAd->Title), function (string $word): bool {
            return mb_strlen($word) >= 3;
        });

        if (empty($titleWords)) {
            return;
        }

        // Find CVs with titles matching any keyword from the job title
        $matchingCvs = CV::query()
            ->where(function ($query) use ($titleWords) {
                foreach ($titleWords as $word) {
                    $query->orWhere('Title', 'LIKE', "%{$word}%");
                }
            })
            ->select('CVID', 'JobSeekerID', 'Title')
            ->get()
            ->unique('JobSeekerID');

        foreach ($matchingCvs as $cv) {
            $user = User::find($cv->JobSeekerID);

            if (! $user) {
                continue;
            }

            // Create database notification
            $notification = Notification::create([
                'UserID' => $user->UserID,
                'Type' => 'Job Match',
                'Content' => "وظيفة جديدة تناسبك: {$jobAd->Title} في {$companyName}",
                'IsRead' => false,
                'CreatedAt' => now(),
            ]);

            // Broadcast via Reverb
            try {
                broadcast(new NotificationReceived($notification))->toOthers();
            } catch (\Exception $e) {
                Log::warning('Broadcast failed for job match notification: '.$e->getMessage());
            }

            // Send email if user settings allow it (default: allowed)
            $settings = NotificationSetting::where('UserID', $user->UserID)->first();
            $emailEnabled = $settings ? $settings->EmailNotifications : true;
            $jobAlertsEnabled = $settings ? $settings->JobAlerts : true;

            if ($emailEnabled && $jobAlertsEnabled) {
                try {
                    Mail::to($user->Email)->send(new NewJobMatchMail($jobAd, $user, $companyName));
                } catch (\Exception $e) {
                    Log::warning('Failed to send job match email', [
                        'user_id' => $user->UserID,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
