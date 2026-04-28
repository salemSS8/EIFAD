<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Provider Pipeline
    |--------------------------------------------------------------------------
    |
    | This defines the order of AI providers to try. If the first one fails,
    | the system will automatically fall back to the next available provider.
    |
    */
    'pipeline' => [
        'gemini' => \App\Domain\Shared\Services\GeminiAIService::class,
        'groq' => \App\Domain\Shared\Services\GroqAIService::class,
        'openrouter' => \App\Domain\Shared\Services\OpenRouterAIService::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    |
    | Cache duration for AI responses to reduce costs and latency.
    |
    */
    'cache_ttl' => 86400, // 24 hours
];
