<?php

namespace App\Domain\Application\Jobs;

use App\Domain\Application\Models\JobApplication;
use App\Domain\AI\Models\CVJobMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job: Compute Candidate Compatibility using Rule-Based Logic (NO AI).
 * 
 * This job calculates compatibility between a candidate and job using
 * deterministic weighted algorithms.
 * 
 * Output:
 * - compatibility_level: HIGH | MEDIUM | LOW
 * - score_breakdown: skills, experience, education (0-100 each)
 * 
 * NO hiring decisions (strong_hire, hire, maybe, no_hire).
 * NO AI is used in this job.
 */
class ComputeCompatibilityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    /**
     * Weights for compatibility calculation.
     */
    private const WEIGHTS = [
        'skills' => 40,
        'experience' => 35,
        'education' => 25,
    ];

    public function __construct(
        public JobApplication $application
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): array
    {
        try {
            // Load related data
            $this->application->load([
                'cv.skills.skill',
                'cv.experiences',
                'cv.education',
                'jobAd.skills.skill',
            ]);

            $cv = $this->application->cv;
            $job = $this->application->jobAd;

            if (!$cv || !$job) {
                Log::warning('ComputeCompatibilityJob: Missing CV or Job', [
                    'application_id' => $this->application->ApplicationID
                ]);
                return ['success' => false, 'error' => 'Missing CV or Job'];
            }

            // Calculate individual scores
            $skillsScore = $this->calculateSkillsMatch($cv, $job);
            $experienceScore = $this->calculateExperienceMatch($cv, $job);
            $educationScore = $this->calculateEducationMatch($cv, $job);

            // Calculate weighted total
            $totalScore =
                ($skillsScore * self::WEIGHTS['skills'] / 100) +
                ($experienceScore * self::WEIGHTS['experience'] / 100) +
                ($educationScore * self::WEIGHTS['education'] / 100);

            // Determine compatibility level
            $compatibilityLevel = $this->determineCompatibilityLevel($totalScore);

            $result = [
                'compatibility_level' => $compatibilityLevel,
                'total_score' => round($totalScore),
                'score_breakdown' => [
                    'skills' => $skillsScore,
                    'experience' => $experienceScore,
                    'education' => $educationScore,
                ],
                'weights' => self::WEIGHTS,
            ];

            // Persist result
            $this->persistResult($result);

            Log::info('ComputeCompatibilityJob: Compatibility computed', [
                'application_id' => $this->application->ApplicationID,
                'compatibility_level' => $compatibilityLevel,
                'total_score' => round($totalScore),
            ]);

            return ['success' => true, 'result' => $result];
        } catch (\Exception $e) {
            Log::error('ComputeCompatibilityJob failed', [
                'application_id' => $this->application->ApplicationID,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Calculate skills match score (0-100).
     */
    private function calculateSkillsMatch($cv, $job): int
    {
        $jobSkills = $job->skills->pluck('skill.SkillName')->map(fn($s) => strtolower($s))->toArray();
        $cvSkills = $cv->skills->pluck('skill.SkillName')->map(fn($s) => strtolower($s))->toArray();

        if (empty($jobSkills)) {
            return 100; // No requirements = full match
        }

        $matchedSkills = array_intersect($jobSkills, $cvSkills);
        $matchPercentage = (count($matchedSkills) / count($jobSkills)) * 100;

        // Bonus for having more skills than required
        $bonus = min(10, count($cvSkills) - count($jobSkills));

        return min(100, (int) round($matchPercentage + max(0, $bonus)));
    }

    /**
     * Calculate experience match score (0-100).
     */
    private function calculateExperienceMatch($cv, $job): int
    {
        $experiences = $cv->experiences;

        if ($experiences->isEmpty()) {
            return 20; // Minimum score for no experience
        }

        // Calculate total years of experience
        $totalYears = 0;
        $relevantExperience = false;
        $jobTitle = strtolower($job->Title ?? '');
        $jobDescription = strtolower($job->Description ?? '');

        foreach ($experiences as $exp) {
            $startDate = strtotime($exp->StartDate ?? '');
            $endDate = $exp->EndDate ? strtotime($exp->EndDate) : time();

            if ($startDate) {
                $years = ($endDate - $startDate) / (365 * 24 * 60 * 60);
                $totalYears += max(0, $years);
            }

            // Check relevance
            $expTitle = strtolower($exp->JobTitle ?? '');
            if (str_contains($jobTitle, $expTitle) || str_contains($expTitle, $jobTitle)) {
                $relevantExperience = true;
            }
        }

        // Score based on years
        if ($totalYears >= 10) {
            $score = 90;
        } elseif ($totalYears >= 5) {
            $score = 75;
        } elseif ($totalYears >= 3) {
            $score = 60;
        } elseif ($totalYears >= 1) {
            $score = 45;
        } else {
            $score = 30;
        }

        // Bonus for relevant experience
        if ($relevantExperience) {
            $score = min(100, $score + 10);
        }

        return $score;
    }

    /**
     * Calculate education match score (0-100).
     */
    private function calculateEducationMatch($cv, $job): int
    {
        $educations = $cv->education;

        if ($educations->isEmpty()) {
            return 30; // Minimum score for no education
        }

        $highestScore = 30;

        foreach ($educations as $edu) {
            $degree = strtolower($edu->DegreeName ?? '');

            if (str_contains($degree, 'phd') || str_contains($degree, 'doctorate')) {
                $highestScore = max($highestScore, 100);
            } elseif (str_contains($degree, 'master') || str_contains($degree, 'mba')) {
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
     * Determine compatibility level from score.
     */
    private function determineCompatibilityLevel(float $score): string
    {
        if ($score >= 70) {
            return 'HIGH';
        } elseif ($score >= 50) {
            return 'MEDIUM';
        } else {
            return 'LOW';
        }
    }

    /**
     * Persist result to database.
     */
    private function persistResult(array $result): void
    {
        // Update application
        $this->application->update([
            'MatchScore' => $result['total_score'],
        ]);

        // Store in CVJobMatch
        CVJobMatch::updateOrCreate(
            [
                'CVID' => $this->application->CVID,
                'JobAdID' => $this->application->JobAdID,
            ],
            [
                'MatchScore' => $result['total_score'],
                'CompatibilityLevel' => $result['compatibility_level'],
                'SkillsScore' => $result['score_breakdown']['skills'],
                'ExperienceScore' => $result['score_breakdown']['experience'],
                'EducationScore' => $result['score_breakdown']['education'],
                'ScoreBreakdown' => json_encode($result['score_breakdown']),
                'ScoringMethod' => 'rule_based',
                'CalculatedAt' => now(),
            ]
        );
    }
}
