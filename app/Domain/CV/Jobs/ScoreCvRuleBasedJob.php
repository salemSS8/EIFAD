<?php

namespace App\Domain\CV\Jobs;

use App\Domain\CV\Models\CV;
use App\Domain\CV\Models\CVAnalysis;
use App\Domain\CV\Services\CvScoringRubric;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job: Score CV using Rule-Based Logic (NO AI).
 * 
 * This job calculates CV scores using deterministic PHP logic
 * based on predefined evaluation rubrics.
 * 
 * Output:
 * - cv_score: 0-100
 * - breakdown: skills, experience, education, completeness, consistency
 * 
 * NO AI is used in this job.
 */
class ScoreCvRuleBasedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public CV $cv
    ) {}

    /**
     * Execute the job.
     */
    public function handle(CvScoringRubric $rubric): array
    {
        try {
            // Load CV with all related data
            $this->cv->load(['skills.skill', 'education', 'experiences', 'languages.language']);

            // Build structured data for scoring
            $cvData = $this->buildCvDataForScoring();

            // Calculate scores using rule-based rubric
            $scores = $rubric->calculateScore($cvData);

            // Persist scores
            $this->persistScores($scores);

            Log::info('ScoreCvRuleBasedJob: CV scored successfully', [
                'cv_id' => $this->cv->CVID,
                'total_score' => $scores['total_score'],
            ]);

            return $scores;
        } catch (\Exception $e) {
            Log::error('ScoreCvRuleBasedJob failed', [
                'cv_id' => $this->cv->CVID,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Build CV data structure for scoring.
     */
    private function buildCvDataForScoring(): array
    {
        return [
            'personal_info' => [
                'name' => $this->cv->jobSeeker->user->FullName ?? null,
                'email' => $this->cv->jobSeeker->user->Email ?? null,
                'phone' => $this->cv->jobSeeker->user->Phone ?? null,
                'location' => $this->cv->jobSeeker->Location ?? null,
            ],
            'summary' => $this->cv->PersonalSummary,
            'skills' => $this->cv->skills->map(function ($cvSkill) {
                return [
                    'name' => $cvSkill->skill->SkillName ?? null,
                    'level' => $cvSkill->SkillLevel ?? null,
                ];
            })->toArray(),
            'experience' => $this->cv->experiences->map(function ($exp) {
                return [
                    'job_title' => $exp->JobTitle,
                    'company_name' => $exp->CompanyName,
                    'start_date' => $exp->StartDate,
                    'end_date' => $exp->EndDate,
                    'responsibilities' => $exp->Responsibilities,
                ];
            })->toArray(),
            'education' => $this->cv->education->map(function ($edu) {
                return [
                    'degree_name' => $edu->DegreeName,
                    'institution' => $edu->Institution,
                    'major' => $edu->Major,
                    'graduation_year' => $edu->GraduationYear,
                ];
            })->toArray(),
            'languages' => $this->cv->languages->map(function ($cvLang) {
                return [
                    'name' => $cvLang->language->LanguageName ?? null,
                    'level' => $cvLang->LanguageLevel ?? null,
                ];
            })->toArray(),
        ];
    }

    /**
     * Persist calculated scores to database.
     */
    private function persistScores(array $scores): void
    {
        CVAnalysis::updateOrCreate(
            ['CVID' => $this->cv->CVID],
            [
                'OverallScore' => $scores['total_score'],
                'SkillsScore' => $scores['breakdown']['skills']['score'] ?? null,
                'ExperienceScore' => $scores['breakdown']['experience']['score'] ?? null,
                'EducationScore' => $scores['breakdown']['education']['score'] ?? null,
                'CompletenessScore' => $scores['breakdown']['completeness']['score'] ?? null,
                'ConsistencyScore' => $scores['breakdown']['consistency']['score'] ?? null,
                'ScoreBreakdown' => json_encode($scores['breakdown']),
                'ScoringMethod' => 'rule_based',
                'ScoredAt' => now(),
            ]
        );
    }
}
