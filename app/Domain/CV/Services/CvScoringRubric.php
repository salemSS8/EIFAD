<?php

namespace App\Domain\CV\Services;

/**
 * CV Scoring Rubric - Rule-Based Evaluation Criteria
 * 
 * This service provides deterministic scoring without AI.
 * Scores are calculated based on predefined rubrics.
 */
class CvScoringRubric
{
    /**
     * Score weights for different CV sections.
     */
    private const WEIGHTS = [
        'skills' => 25,
        'experience' => 30,
        'education' => 20,
        'completeness' => 15,
        'consistency' => 10,
    ];

    /**
     * Calculate total CV score based on structured data.
     *
     * @param array $cvData Structured CV data
     * @return array Scores breakdown
     */
    public function calculateScore(array $cvData): array
    {
        $skillsScore = $this->scoreSkills($cvData['skills'] ?? []);
        $experienceScore = $this->scoreExperience($cvData['experience'] ?? []);
        $educationScore = $this->scoreEducation($cvData['education'] ?? []);
        $completenessScore = $this->scoreCompleteness($cvData);
        $consistencyScore = $this->scoreConsistency($cvData);

        $totalScore =
            ($skillsScore * self::WEIGHTS['skills'] / 100) +
            ($experienceScore * self::WEIGHTS['experience'] / 100) +
            ($educationScore * self::WEIGHTS['education'] / 100) +
            ($completenessScore * self::WEIGHTS['completeness'] / 100) +
            ($consistencyScore * self::WEIGHTS['consistency'] / 100);

        return [
            'total_score' => round($totalScore),
            'breakdown' => [
                'skills' => [
                    'score' => $skillsScore,
                    'weight' => self::WEIGHTS['skills'],
                    'weighted_score' => round($skillsScore * self::WEIGHTS['skills'] / 100),
                ],
                'experience' => [
                    'score' => $experienceScore,
                    'weight' => self::WEIGHTS['experience'],
                    'weighted_score' => round($experienceScore * self::WEIGHTS['experience'] / 100),
                ],
                'education' => [
                    'score' => $educationScore,
                    'weight' => self::WEIGHTS['education'],
                    'weighted_score' => round($educationScore * self::WEIGHTS['education'] / 100),
                ],
                'completeness' => [
                    'score' => $completenessScore,
                    'weight' => self::WEIGHTS['completeness'],
                    'weighted_score' => round($completenessScore * self::WEIGHTS['completeness'] / 100),
                ],
                'consistency' => [
                    'score' => $consistencyScore,
                    'weight' => self::WEIGHTS['consistency'],
                    'weighted_score' => round($consistencyScore * self::WEIGHTS['consistency'] / 100),
                ],
            ],
        ];
    }

    /**
     * Score skills section (0-100).
     */
    private function scoreSkills(array $skills): int
    {
        if (empty($skills)) {
            return 0;
        }

        $count = count($skills);

        // Base score by count
        if ($count >= 10) {
            $baseScore = 100;
        } elseif ($count >= 7) {
            $baseScore = 85;
        } elseif ($count >= 5) {
            $baseScore = 70;
        } elseif ($count >= 3) {
            $baseScore = 50;
        } else {
            $baseScore = 30;
        }

        return $baseScore;
    }

    /**
     * Score experience section (0-100).
     */
    private function scoreExperience(array $experiences): int
    {
        if (empty($experiences)) {
            return 0;
        }

        $score = 0;
        $totalYears = 0;

        foreach ($experiences as $exp) {
            // Points for having description
            if (!empty($exp['description'] ?? $exp['responsibilities'] ?? null)) {
                $score += 15;
            }

            // Calculate years
            if (isset($exp['start_date'])) {
                $startDate = strtotime($exp['start_date']);
                $endDate = isset($exp['end_date']) ? strtotime($exp['end_date']) : time();
                $years = ($endDate - $startDate) / (365 * 24 * 60 * 60);
                $totalYears += max(0, $years);
            }
        }

        // Score by years of experience
        if ($totalYears >= 10) {
            $score += 60;
        } elseif ($totalYears >= 5) {
            $score += 50;
        } elseif ($totalYears >= 3) {
            $score += 35;
        } elseif ($totalYears >= 1) {
            $score += 20;
        } else {
            $score += 10;
        }

        // Number of positions
        $score += min(25, count($experiences) * 5);

        return min(100, $score);
    }

    /**
     * Score education section (0-100).
     */
    private function scoreEducation(array $education): int
    {
        if (empty($education)) {
            return 0;
        }

        $score = 0;
        $highestDegree = '';

        foreach ($education as $edu) {
            $degree = strtolower($edu['degree'] ?? $edu['degree_name'] ?? '');

            // Score by degree level
            if (str_contains($degree, 'phd') || str_contains($degree, 'doctorate')) {
                $degreeScore = 100;
                $highestDegree = 'phd';
            } elseif (str_contains($degree, 'master')) {
                $degreeScore = 90;
                if ($highestDegree !== 'phd') $highestDegree = 'master';
            } elseif (str_contains($degree, 'bachelor')) {
                $degreeScore = 75;
                if (!in_array($highestDegree, ['phd', 'master'])) $highestDegree = 'bachelor';
            } elseif (str_contains($degree, 'diploma') || str_contains($degree, 'associate')) {
                $degreeScore = 50;
            } else {
                $degreeScore = 30;
            }

            $score = max($score, $degreeScore);
        }

        return $score;
    }

    /**
     * Score CV completeness (0-100).
     */
    private function scoreCompleteness(array $cvData): int
    {
        $requiredFields = [
            'personal_info.name',
            'personal_info.email',
            'skills',
            'experience',
            'education',
        ];

        $optionalFields = [
            'personal_info.phone',
            'personal_info.location',
            'summary',
            'languages',
            'certifications',
        ];

        $score = 0;
        $requiredWeight = 70;
        $optionalWeight = 30;

        // Check required fields
        $requiredFilled = 0;
        foreach ($requiredFields as $field) {
            $value = $this->getNestedValue($cvData, $field);
            if (!empty($value)) {
                $requiredFilled++;
            }
        }
        $score += ($requiredFilled / count($requiredFields)) * $requiredWeight;

        // Check optional fields
        $optionalFilled = 0;
        foreach ($optionalFields as $field) {
            $value = $this->getNestedValue($cvData, $field);
            if (!empty($value)) {
                $optionalFilled++;
            }
        }
        $score += ($optionalFilled / count($optionalFields)) * $optionalWeight;

        return (int) round($score);
    }

    /**
     * Score CV consistency (0-100).
     */
    private function scoreConsistency(array $cvData): int
    {
        $score = 100;
        $issues = [];

        // Check for date gaps in experience
        $experiences = $cvData['experience'] ?? [];
        if (count($experiences) > 1) {
            usort($experiences, function ($a, $b) {
                return strtotime($b['start_date'] ?? '1900-01-01') - strtotime($a['start_date'] ?? '1900-01-01');
            });

            for ($i = 0; $i < count($experiences) - 1; $i++) {
                $endDate = strtotime($experiences[$i]['end_date'] ?? date('Y-m-d'));
                $nextStart = strtotime($experiences[$i + 1]['start_date'] ?? '1900-01-01');

                $gapMonths = ($endDate - $nextStart) / (30 * 24 * 60 * 60);

                if ($gapMonths > 12) {
                    $score -= 10;
                    $issues[] = 'Large gap in experience';
                }
            }
        }

        // Check for incomplete entries
        foreach ($cvData['experience'] ?? [] as $exp) {
            if (empty($exp['description'] ?? $exp['responsibilities'] ?? null)) {
                $score -= 5;
            }
        }

        return max(0, $score);
    }

    /**
     * Get nested array value using dot notation.
     */
    private function getNestedValue(array $array, string $key)
    {
        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }

        return $value;
    }
}
