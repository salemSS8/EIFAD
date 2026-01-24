<?php

namespace App\Domain\Shared\Contracts;

/**
 * Interface for AI service implementations.
 * Allows swapping AI providers without changing business logic.
 */
interface AIServiceInterface
{
    /**
     * Analyze a CV and extract structured data.
     */
    public function analyzeCV(string $cvContent): array;

    /**
     * Generate job recommendations based on user profile.
     */
    public function generateJobRecommendations(array $userProfile, array $availableJobs): array;

    /**
     * Score a candidate for a specific job.
     */
    public function scoreCandidateForJob(array $candidateProfile, array $jobRequirements): array;

    /**
     * Suggest skill gaps and learning paths.
     */
    public function suggestSkillGaps(array $currentSkills, array $targetRole): array;

    /**
     * Generate a career roadmap.
     */
    public function generateCareerRoadmap(array $userProfile, string $targetRole): array;
}
