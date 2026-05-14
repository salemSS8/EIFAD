<?php

namespace App\Domain\Shared\Contracts;

/**
 * Interface for AI service implementations.
 * Allows swapping AI providers without changing business logic.
 */
interface AIServiceInterface
{
    /**
     * Explain CV Analysis - TEXT ONLY.
     */
    public function explainCvAnalysis(array $context): array;

    /**
     * Explain Compatibility - TEXT ONLY.
     */
    public function explainCompatibility(array $context): array;

    /**
     * Explain Job Match - TEXT ONLY.
     */
    public function explainJobMatch(array $matchData): array;

    /**
     * Generate a career roadmap.
     */
    public function generateCareerRoadmap(array $userProfile, string $targetRole): array;

    /**
     * Verify a certificate and return analysis results.
     */
    public function verifyCertificate(array $certificateData): array;

    /**
     * Categorize a job ad into an industry.
     */
    public function categorizeJobAd(array $jobData, array $existingIndustries): string;

    /**
     * Screen an applicant against a job ad.
     */
    public function screenApplicant(array $jobData, array $cvData): array;
}
