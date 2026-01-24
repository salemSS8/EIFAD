<?php

namespace App\Domain\Job\Jobs;

use App\Domain\Job\Models\JobAd;
use App\Domain\CV\Models\CV;
use App\Domain\AI\Models\CVJobMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job: Compute Match Score using Rule-Based Weighted Algorithm (NO AI).
 * 
 * This job calculates match scores between a CV and multiple jobs
 * using deterministic weighted algorithms.
 * 
 * Output:
 * - match_score: 0-100
 * - detailed_breakdown: skills, experience, education weights
 * 
 * NO AI is used in this job.
 */
class ComputeMatchScoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    /**
     * Weights for match calculation.
     */
    private const WEIGHTS = [
        'skills_exact' => 30,
        'skills_related' => 15,
        'experience_years' => 25,
        'experience_relevance' => 15,
        'education' => 15,
    ];

    public function __construct(
        public CV $cv,
        public array $jobIds = []
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): array
    {
        try {
            $this->cv->load(['skills.skill', 'experiences', 'education']);

            // Get jobs to match against
            $jobs = JobAd::whereIn('JobAdID', $this->jobIds)
                ->orWhere(fn($q) => empty($this->jobIds) && $q->where('Status', 'Active'))
                ->with('skills.skill')
                ->take(50)
                ->get();

            $results = [];

            foreach ($jobs as $job) {
                $matchResult = $this->computeMatchForJob($job);
                $results[] = $matchResult;

                // Persist result
                $this->persistMatch($job, $matchResult);
            }

            // Sort by match score descending
            usort($results, fn($a, $b) => $b['match_score'] - $a['match_score']);

            Log::info('ComputeMatchScoreJob: Matches computed', [
                'cv_id' => $this->cv->CVID,
                'jobs_matched' => count($results),
            ]);

            return [
                'success' => true,
                'cv_id' => $this->cv->CVID,
                'matches' => $results,
            ];
        } catch (\Exception $e) {
            Log::error('ComputeMatchScoreJob failed', [
                'cv_id' => $this->cv->CVID,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Compute match score for a single job.
     */
    private function computeMatchForJob(JobAd $job): array
    {
        // Skills matching
        $skillsExactScore = $this->calculateExactSkillsMatch($job);
        $skillsRelatedScore = $this->calculateRelatedSkillsMatch($job);

        // Experience matching
        $experienceYearsScore = $this->calculateExperienceYearsScore();
        $experienceRelevanceScore = $this->calculateExperienceRelevance($job);

        // Education matching
        $educationScore = $this->calculateEducationScore($job);

        // Calculate weighted total
        $totalScore =
            ($skillsExactScore * self::WEIGHTS['skills_exact'] / 100) +
            ($skillsRelatedScore * self::WEIGHTS['skills_related'] / 100) +
            ($experienceYearsScore * self::WEIGHTS['experience_years'] / 100) +
            ($experienceRelevanceScore * self::WEIGHTS['experience_relevance'] / 100) +
            ($educationScore * self::WEIGHTS['education'] / 100);

        return [
            'job_id' => $job->JobAdID,
            'job_title' => $job->Title,
            'match_score' => (int) round($totalScore),
            'breakdown' => [
                'skills_exact' => [
                    'score' => $skillsExactScore,
                    'weight' => self::WEIGHTS['skills_exact'],
                ],
                'skills_related' => [
                    'score' => $skillsRelatedScore,
                    'weight' => self::WEIGHTS['skills_related'],
                ],
                'experience_years' => [
                    'score' => $experienceYearsScore,
                    'weight' => self::WEIGHTS['experience_years'],
                ],
                'experience_relevance' => [
                    'score' => $experienceRelevanceScore,
                    'weight' => self::WEIGHTS['experience_relevance'],
                ],
                'education' => [
                    'score' => $educationScore,
                    'weight' => self::WEIGHTS['education'],
                ],
            ],
        ];
    }

    /**
     * Calculate exact skills match (0-100).
     */
    private function calculateExactSkillsMatch(JobAd $job): int
    {
        $jobSkills = $job->skills->pluck('skill.SkillName')
            ->map(fn($s) => strtolower(trim($s)))
            ->filter()
            ->toArray();

        $cvSkills = $this->cv->skills->pluck('skill.SkillName')
            ->map(fn($s) => strtolower(trim($s)))
            ->filter()
            ->toArray();

        if (empty($jobSkills)) {
            return 100;
        }

        $matched = count(array_intersect($jobSkills, $cvSkills));
        return (int) round(($matched / count($jobSkills)) * 100);
    }

    /**
     * Calculate related skills match (0-100).
     */
    private function calculateRelatedSkillsMatch(JobAd $job): int
    {
        // Simple related skills detection based on categories
        $cvSkillCategories = $this->cv->skills
            ->pluck('skill.CategoryID')
            ->filter()
            ->unique()
            ->toArray();

        $jobSkillCategories = $job->skills
            ->pluck('skill.CategoryID')
            ->filter()
            ->unique()
            ->toArray();

        if (empty($jobSkillCategories)) {
            return 100;
        }

        $matched = count(array_intersect($cvSkillCategories, $jobSkillCategories));
        return (int) round(($matched / count($jobSkillCategories)) * 100);
    }

    /**
     * Calculate experience years score (0-100).
     */
    private function calculateExperienceYearsScore(): int
    {
        $totalYears = 0;

        foreach ($this->cv->experiences as $exp) {
            $startDate = strtotime($exp->StartDate ?? '');
            $endDate = $exp->EndDate ? strtotime($exp->EndDate) : time();

            if ($startDate) {
                $years = ($endDate - $startDate) / (365 * 24 * 60 * 60);
                $totalYears += max(0, $years);
            }
        }

        if ($totalYears >= 10) return 100;
        if ($totalYears >= 7) return 90;
        if ($totalYears >= 5) return 75;
        if ($totalYears >= 3) return 60;
        if ($totalYears >= 1) return 40;
        return 20;
    }

    /**
     * Calculate experience relevance (0-100).
     */
    private function calculateExperienceRelevance(JobAd $job): int
    {
        $jobTitle = strtolower($job->Title ?? '');
        $jobKeywords = array_filter(explode(' ', $jobTitle));

        $relevanceScore = 0;

        foreach ($this->cv->experiences as $exp) {
            $expTitle = strtolower($exp->JobTitle ?? '');

            foreach ($jobKeywords as $keyword) {
                if (strlen($keyword) > 3 && str_contains($expTitle, $keyword)) {
                    $relevanceScore += 20;
                    break;
                }
            }
        }

        return min(100, $relevanceScore);
    }

    /**
     * Calculate education score (0-100).
     */
    private function calculateEducationScore(JobAd $job): int
    {
        if ($this->cv->education->isEmpty()) {
            return 30;
        }

        $highestScore = 30;

        foreach ($this->cv->education as $edu) {
            $degree = strtolower($edu->DegreeName ?? '');

            if (str_contains($degree, 'phd') || str_contains($degree, 'doctorate')) {
                $highestScore = 100;
            } elseif (str_contains($degree, 'master')) {
                $highestScore = max($highestScore, 85);
            } elseif (str_contains($degree, 'bachelor')) {
                $highestScore = max($highestScore, 70);
            } elseif (str_contains($degree, 'diploma')) {
                $highestScore = max($highestScore, 50);
            }
        }

        return $highestScore;
    }

    /**
     * Persist match result to database.
     */
    private function persistMatch(JobAd $job, array $result): void
    {
        CVJobMatch::updateOrCreate(
            [
                'CVID' => $this->cv->CVID,
                'JobAdID' => $job->JobAdID,
            ],
            [
                'MatchScore' => $result['match_score'],
                'ScoreBreakdown' => json_encode($result['breakdown']),
                'ScoringMethod' => 'rule_based_weighted',
                'CalculatedAt' => now(),
            ]
        );
    }
}
