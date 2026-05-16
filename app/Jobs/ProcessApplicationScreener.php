<?php

namespace App\Jobs;

use App\Domain\Application\Models\JobApplication;
use App\Domain\Shared\Services\AiServiceOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessApplicationScreener implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public JobApplication $application
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AiServiceOrchestrator $orchestrator): void
    {
        try {
            // Eager load necessary relationships
            $this->application->load([
                'jobAd',
                'cv.skills.skill',
                'cv.experiences',
                'cv.education',
                'cv.languages.language',
            ]);

            $jobAd = $this->application->jobAd;
            $cv = $this->application->cv;

            if (! $jobAd || ! $cv) {
                Log::warning("Agent screener failed: Missing JobAd or CV for Application ID {$this->application->ApplicationID}");

                return;
            }

            // 1. تحضير بيانات الوظيفة
            $jobData = [
                'Title' => $jobAd->Title,
                'Description' => $jobAd->Description,
                'Responsibilities' => $jobAd->Responsibilities,
                'Requirements' => $jobAd->Requirements,
            ];

            // 2. تحضير بيانات السيرة الذاتية
            $cvData = [
                'Title' => $cv->Title,
                'PersonalSummary' => $cv->PersonalSummary,
                'Skills' => $cv->skills()->with('skill')->get()->map(fn ($s) => $s->skill->SkillName ?? '')->toArray(),
                'Experience' => $cv->experiences->map(fn ($e) => "{$e->JobTitle} at {$e->CompanyName} ({$e->StartDate} to {$e->EndDate}): {$e->Responsibilities}")->toArray(),
                'Education' => $cv->education->map(fn ($e) => "{$e->DegreeName} in {$e->Major} from {$e->Institution}")->toArray(),
                'Languages' => $cv->languages()->with('language')->get()->map(fn ($l) => ($l->language->LanguageName ?? '')." ({$l->LanguageLevel})")->toArray(),
                'VerificationStatus' => $cv->jobSeeker->Status ?? 'notrusted',
            ];

            // 3. استدعاء الذكاء الاصطناعي مع Failover (Gemini → Groq → OpenRouter)
            $response = $orchestrator->screenApplicant($jobData, $cvData);

            // 4. ترتيب البيانات وحفظها
            DB::transaction(function () use ($response) {
                $missingSkillsText = ! empty($response['missing_skills'])
                    ? "\nالمهارات الناقصة: ".implode('، ', $response['missing_skills'])
                    : '';

                $this->application->update([
                    'MatchScore' => $response['match_score'] ?? 0,
                    'Notes' => ($response['notes'] ?? '').$missingSkillsText,
                ]);
            });
        } catch (\Exception $e) {
            Log::error("Failed to process applicant screener for application {$this->application->ApplicationID}: ".$e->getMessage());
        }
    }
}
