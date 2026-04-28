<?php

namespace App\Domain\Shared\Services;

use App\Domain\Shared\Contracts\AIServiceInterface;
use App\Domain\Shared\Traits\HasAiPrompts;
use App\Domain\Shared\Traits\ParsesAiResponses;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Groq AI Service - Fallback provider using Llama models.
 */
class GroqAIService implements AIServiceInterface
{
    use HasAiPrompts, ParsesAiResponses;

    private string $apiKey;
    private string $baseUrl;
    private string $model;

    public function __construct()
    {
        $this->apiKey = env('GROQ_API_KEY', '');
        $this->baseUrl = env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1/chat/completions');
        $this->model = env('GROQ_MODEL', 'llama-3.1-8b-instant');
    }

    public function explainCvAnalysis(array $context): array
    {
        $prompt = $this->buildCvExplanationPrompt($context);
        $response = $this->callGroq($prompt);
        $result = $this->parseJsonResponse($response);
        $result['_meta'] = ['model' => $this->model, 'provider' => 'groq'];
        return $result;
    }

    public function explainCompatibility(array $context): array
    {
        $prompt = $this->buildCompatibilityExplanationPrompt($context);
        $response = $this->callGroq($prompt);
        $result = $this->parseJsonResponse($response);
        $result['_meta'] = ['model' => $this->model, 'provider' => 'groq'];
        return $result;
    }

    public function explainJobMatch(array $matchData): array
    {
        $prompt = $this->buildMatchExplanationPrompt($matchData);
        $response = $this->callGroq($prompt);
        $result = $this->parseJsonResponse($response);
        $result['_meta'] = ['model' => $this->model, 'provider' => 'groq'];
        return $result;
    }

    public function generateCareerRoadmap(array $userProfile, string $targetRole): array
    {
        $prompt = $this->buildCareerRoadmapPrompt($userProfile, $targetRole);
        $response = $this->callGroq($prompt);
        $result = $this->parseJsonResponse($response);
        $result['_meta'] = ['model' => $this->model, 'provider' => 'groq'];
        return $result;
    }

    /**
     * Make API call to Groq (OpenAI compatible).
     */
    private function callGroq(string $prompt): string
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Groq API key not configured');
        }

        try {
            $response = Http::connectTimeout(60)->timeout(180)->withoutVerifying()
                ->withToken($this->apiKey)
                ->post($this->baseUrl, [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if ($response->failed()) {
                Log::error('Groq API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \RuntimeException('Groq API request failed');
            }

            $data = $response->json();
            return $data['choices'][0]['message']['content'] ?? '';
        } catch (\Exception $e) {
            Log::error('Groq API exception', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
