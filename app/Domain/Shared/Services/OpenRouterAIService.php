<?php

namespace App\Domain\Shared\Services;

use App\Domain\Shared\Contracts\AIServiceInterface;
use App\Domain\Shared\Traits\HasAiPrompts;
use App\Domain\Shared\Traits\ParsesAiResponses;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenRouter AI Service - Final fallback provider.
 */
class OpenRouterAIService implements AIServiceInterface
{
    use HasAiPrompts, ParsesAiResponses;

    private string $apiKey;
    private string $baseUrl;
    private string $model;

    public function __construct()
    {
        $this->apiKey = env('OPENROUTER_API_KEY', '');
        $this->baseUrl = env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1/chat/completions');
        $this->model = env('OPENROUTER_MODEL', 'llama-3.1-8b-instant');
    }

    public function explainCvAnalysis(array $context): array
    {
        $prompt = $this->buildCvExplanationPrompt($context);
        $response = $this->callOpenRouter($prompt);
        $result = $this->parseJsonResponse($response);
        $result['_meta'] = ['model' => $this->model, 'provider' => 'openrouter'];
        return $result;
    }

    public function explainCompatibility(array $context): array
    {
        $prompt = $this->buildCompatibilityExplanationPrompt($context);
        $response = $this->callOpenRouter($prompt);
        $result = $this->parseJsonResponse($response);
        $result['_meta'] = ['model' => $this->model, 'provider' => 'openrouter'];
        return $result;
    }

    public function explainJobMatch(array $matchData): array
    {
        $prompt = $this->buildMatchExplanationPrompt($matchData);
        $response = $this->callOpenRouter($prompt);
        $result = $this->parseJsonResponse($response);
        $result['_meta'] = ['model' => $this->model, 'provider' => 'openrouter'];
        return $result;
    }

    public function generateCareerRoadmap(array $userProfile, string $targetRole): array
    {
        $prompt = $this->buildCareerRoadmapPrompt($userProfile, $targetRole);
        $response = $this->callOpenRouter($prompt);
        $result = $this->parseJsonResponse($response);
        $result['_meta'] = ['model' => $this->model, 'provider' => 'openrouter'];
        return $result;
    }

    /**
     * Make API call to OpenRouter.
     */
    private function callOpenRouter(string $prompt): string
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('OpenRouter API key not configured');
        }

        try {
            $response = Http::connectTimeout(60)->timeout(180)->withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'HTTP-Referer' => config('app.url'),
                    'X-Title' => config('app.name'),
                ])
                ->post($this->baseUrl, [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0,
                ]);

            if ($response->failed()) {
                Log::error('OpenRouter API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \RuntimeException('OpenRouter API request failed');
            }

            $data = $response->json();
            return $data['choices'][0]['message']['content'] ?? '';
        } catch (\Exception $e) {
            Log::error('OpenRouter API exception', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
