<?php

namespace App\Jobs;

use App\Ai\Agents\ApplicantScreener;
use App\Domain\Application\Models\JobApplication;
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
    public function handle(): void
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

            // 1. تهيئة الوكيل الذكي
            $screener = new ApplicantScreener($jobAd, $cv);

            // 2. استدعاء الذكاء الاصطناعي بالطريقة الرسمية الصحيحة
            $response = $screener->prompt('ابدأ تحليل السيرة الذاتية بناءً على التعليمات المعطاة لك.');

            // 3. ترتيب البيانات وحفظها (النتيجة تعود كمصفوفة بفضل HasStructuredOutput)
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
            // الآن إذا حدث خطأ سنراه فوراً في الـ Terminal بدلاً من الصمت!
            dump('AI Error: '.$e->getMessage());
            Log::error("Failed to process applicant screener for application {$this->application->ApplicationID}: ".$e->getMessage());
        }
    }
}
