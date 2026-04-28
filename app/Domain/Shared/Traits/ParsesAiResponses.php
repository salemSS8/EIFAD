<?php

namespace App\Domain\Shared\Traits;

use Illuminate\Support\Facades\Log;

trait ParsesAiResponses
{
    protected function parseJsonResponse(string $response): array
    {
        // Step 1: Strip markdown code fences
        $cleaned = preg_replace('/```json\s*/', '', $response);
        $cleaned = preg_replace('/```\s*/', '', $cleaned);
        $cleaned = trim($cleaned);

        // Step 2: Try parsing the cleaned response directly
        $decoded = json_decode($cleaned, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Step 3: Some LLMs prepend free text before JSON — extract the JSON object
        $firstBrace = strpos($cleaned, '{');
        $lastBrace = strrpos($cleaned, '}');

        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $jsonCandidate = substr($cleaned, $firstBrace, $lastBrace - $firstBrace + 1);
            $decoded = json_decode($jsonCandidate, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        Log::warning('Failed to parse AI response as JSON', [
            'response_prefix' => substr($cleaned, 0, 100),
            'full_length' => strlen($cleaned),
            'error' => json_last_error_msg(),
        ]);

        return ['raw_response' => $cleaned, 'parse_error' => true];
    }
}
