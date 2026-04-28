<?php

namespace App\Domain\Shared\Services;

use App\Domain\Shared\Contracts\AIServiceInterface;
use App\Domain\Shared\Traits\HasAiPrompts;
use App\Domain\Shared\Traits\ParsesAiResponses;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gemini AI Service - Refactored for Explanation-Only Role.
 *
 * ⚠️ IMPORTANT: This service is restricted to EXPLANATION and TEXT GENERATION only.
 *
 * Gemini must NOT:
 * ❌ Parse or extract data
 * ❌ Calculate scores
 * ❌ Make hiring decisions
 * ❌ Perform matching algorithms
 *
 * Gemini CAN:
 * ✅ Explain analysis results
 * ✅ Generate human-readable descriptions
 * ✅ Provide recommendations as text
 * ✅ Create career roadmaps (text only)
 */
class GeminiAIService implements AIServiceInterface
{
    use HasAiPrompts, ParsesAiResponses;
    private string $apiKey;

    private string $baseUrl;

    private string $model;

    private const PROMPT_VERSION = '2.1.0';

    public function __construct()
    {
        $this->apiKey = config('gemini.api_key', '');
        $this->baseUrl = config('gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');
        $this->model = config('gemini.model', 'gemini-pro');
    }

    // =========================================================================
    // NEW EXPLANATION-ONLY METHODS (Aligned with Documentation)
    // =========================================================================

    /**
     * Explain CV Analysis - TEXT ONLY, NO SCORING.
     *
     * Input: Pre-calculated scores and CV data
     * Output: Human-readable explanation
     *
     * @param  array  $context  CV data with scores
     * @return array Textual explanations
     */
    public function explainCvAnalysis(array $context): array
    {
        $prompt = $this->buildCvExplanationPrompt($context);
        $inputHash = $this->generateInputHash($context);

        // Check cache
        $cached = $this->getCachedResponse($inputHash);
        if ($cached) {
            return $cached;
        }

        $response = $this->callGemini($prompt);
        $result = $this->parseExplanationResponse($response);

        // Store tracking info
        $result['_meta'] = [
            'model' => $this->model,
            'provider' => 'gemini',
            'prompt_version' => self::PROMPT_VERSION,
            'input_hash' => $inputHash,
            'generated_at' => now()->toIso8601String(),
        ];

        // Cache response
        $this->cacheResponse($inputHash, $result);

        return $result;
    }

    /**
     * Explain Compatibility - TEXT ONLY, NO DECISIONS.
     *
     * Input: Pre-calculated compatibility scores
     * Output: Why compatibility is HIGH/MEDIUM/LOW
     *
     * ❌ NO: strong_hire, hire, maybe, no_hire
     */
    public function explainCompatibility(array $context): array
    {
        $prompt = $this->buildCompatibilityExplanationPrompt($context);
        $inputHash = $this->generateInputHash($context);

        $cached = $this->getCachedResponse($inputHash);
        if ($cached) {
            return $cached;
        }

        $response = $this->callGemini($prompt);
        $result = $this->parseExplanationResponse($response);

        $result['_meta'] = [
            'model' => $this->model,
            'prompt_version' => self::PROMPT_VERSION,
            'input_hash' => $inputHash,
        ];

        $this->cacheResponse($inputHash, $result);

        return $result;
    }

    /**
     * Explain Job Match - TEXT ONLY.
     *
     * Input: Pre-calculated match scores
     * Output: Human-readable explanation of match reasons
     */
    public function explainJobMatch(array $matchData): array
    {
        $prompt = $this->buildMatchExplanationPrompt($matchData);
        $inputHash = $this->generateInputHash($matchData);

        $cached = $this->getCachedResponse($inputHash);
        if ($cached) {
            return $cached;
        }

        $response = $this->callGemini($prompt);
        $result = $this->parseExplanationResponse($response);

        $result['_meta'] = [
            'model' => $this->model,
            'prompt_version' => self::PROMPT_VERSION,
            'input_hash' => $inputHash,
        ];

        $this->cacheResponse($inputHash, $result);

        return $result;
    }

    /**
     * Generate Career Roadmap - TEXT ONLY.
     *
     * Input: Skill gaps (already computed), target role
     * Output: Ordered roadmap steps (no scoring, no guarantees)
     */
    public function generateCareerRoadmap(array $userProfile, string $targetRole): array
    {
        $prompt = $this->buildCareerRoadmapPrompt($userProfile, $targetRole);
        $inputHash = $this->generateInputHash(['profile' => $userProfile, 'role' => $targetRole]);

        $cached = $this->getCachedResponse($inputHash);
        if ($cached) {
            return $cached;
        }

        $response = $this->callGemini($prompt);
        $result = $this->parseCareerRoadmapResponse($response);

        $result['_meta'] = [
            'model' => $this->model,
            'prompt_version' => self::PROMPT_VERSION,
            'input_hash' => $inputHash,
        ];

        $this->cacheResponse($inputHash, $result);

        return $result;
    }

    // =========================================================================
    // API CALL & RESPONSE HANDLING
    // =========================================================================

    /**
     * Make API call to Gemini with temperature=0 for reproducibility.
     */
    private function callGemini(string $prompt): string
    {
        if (empty($this->apiKey)) {
            Log::warning('Gemini API key not configured');

            return '{"error": "API key not configured"}';
        }

        $url = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";

        try {
            $response = Http::connectTimeout(60)->timeout(180)->withoutVerifying()->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0,  // ⚠️ Must be 0 for reproducibility
                    'topK' => 1,
                    'topP' => 1,
                    'maxOutputTokens' => 8192,
                ],
            ]);

            if ($response->failed()) {
                Log::error('Gemini API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \RuntimeException('Gemini API request failed');
            }

            $data = $response->json();
            $candidate = $data['candidates'][0] ?? null;
            $text = $candidate['content']['parts'][0]['text'] ?? '';
            $finishReason = $candidate['finishReason'] ?? 'UNKNOWN';

            if ($finishReason !== 'STOP') {
                Log::warning('Gemini response finished with non-stop reason', [
                    'finishReason' => $finishReason,
                    'text_length' => strlen($text),
                ]);
            }

            return $text;
        } catch (\Exception $e) {
            Log::error('Gemini API exception', ['error' => $e->getMessage()]);
            throw $e;
        }
    }


    // =========================================================================
    // PROMPT BUILDERS (Explanation-Only - No Scoring)
    // =========================================================================

    private function parseExplanationResponse(string $response): array
    {
        return $this->parseJsonResponse($response);
    }

    private function parseCareerRoadmapResponse(string $response): array
    {
        return $this->parseJsonResponse($response);
    }

    // =========================================================================
    // CACHING & TRACKING
    // =========================================================================

    /**
     * Generate hash for input to enable caching.
     */
    private function generateInputHash(array $input): string
    {
        return md5(json_encode($input).self::PROMPT_VERSION);
    }

    /**
     * Get cached response if available.
     */
    private function getCachedResponse(string $inputHash): ?array
    {
        $cacheKey = "gemini_response_{$inputHash}";

        return Cache::get($cacheKey);
    }

    /**
     * Cache response for future use.
     */
    private function cacheResponse(string $inputHash, array $response): void
    {
        $cacheKey = "gemini_response_{$inputHash}";
        Cache::put($cacheKey, $response, now()->addHours(24));
    }
}
