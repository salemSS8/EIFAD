<?php

namespace App\Domain\Shared\Services;

use App\Domain\Shared\Contracts\AIServiceInterface;
use Illuminate\Support\Facades\Log;

/**
 * AI Service Orchestrator - Handles fallback across multiple providers.
 */
class AiServiceOrchestrator implements AIServiceInterface
{
    /**
     * @param  AIServiceInterface[]  $providers
     */
    public function __construct(private array $providers) {}

    public function explainCvAnalysis(array $context): array
    {
        return $this->executeWithFallback(fn ($p) => $p->explainCvAnalysis($context));
    }

    public function explainCompatibility(array $context): array
    {
        return $this->executeWithFallback(fn ($p) => $p->explainCompatibility($context));
    }

    public function explainJobMatch(array $matchData): array
    {
        return $this->executeWithFallback(fn ($p) => $p->explainJobMatch($matchData));
    }

    public function generateCareerRoadmap(array $userProfile, string $targetRole): array
    {
        return $this->executeWithFallback(fn ($p) => $p->generateCareerRoadmap($userProfile, $targetRole));
    }

    public function verifyCertificate(array $certificateData): array
    {
        return $this->executeWithFallback(fn ($p) => $p->verifyCertificate($certificateData));
    }

    public function categorizeJobAd(array $jobData, array $existingIndustries): string
    {
        return $this->executeWithFallbackString(fn ($p) => $p->categorizeJobAd($jobData, $existingIndustries));
    }

    public function screenApplicant(array $jobData, array $cvData): array
    {
        return $this->executeWithFallback(fn ($p) => $p->screenApplicant($jobData, $cvData));
    }

    /**
     * Execute a method on providers sequentially until one succeeds.
     */
    private function executeWithFallback(callable $callback): array
    {
        set_time_limit(300);
        $errors = [];

        foreach ($this->providers as $provider) {
            try {
                return $callback($provider);
            } catch (\Exception $e) {
                $providerName = get_class($provider);
                Log::warning("AI Provider {$providerName} failed, trying next fallback...", [
                    'error' => $e->getMessage(),
                ]);
                $errors[] = "{$providerName}: {$e->getMessage()}";
            }
        }

        Log::critical('All AI providers failed.', ['errors' => $errors]);
        throw new \RuntimeException('All AI providers failed: '.implode(' | ', $errors));
    }

    /**
     * Execute a method on providers sequentially until one succeeds (string return).
     */
    private function executeWithFallbackString(callable $callback): string
    {
        set_time_limit(300);
        $errors = [];

        foreach ($this->providers as $provider) {
            try {
                return $callback($provider);
            } catch (\Exception $e) {
                $providerName = get_class($provider);
                Log::warning("AI Provider {$providerName} failed, trying next fallback...", [
                    'error' => $e->getMessage(),
                ]);
                $errors[] = "{$providerName}: {$e->getMessage()}";
            }
        }

        Log::critical('All AI providers failed.', ['errors' => $errors]);
        throw new \RuntimeException('All AI providers failed: '.implode(' | ', $errors));
    }
}
