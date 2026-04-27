<?php

namespace App\Domain\Shared\Services;

use App\Domain\Shared\Contracts\AIServiceInterface;
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
    // DEPRECATED METHODS (Kept for backward compatibility, marked for removal)
    // =========================================================================

    /**
     * @deprecated Use ParseCvJob + ScoreCvRuleBasedJob + explainCvAnalysis instead
     */
    public function analyzeCV(string $cvContent): array
    {
        Log::warning('GeminiAIService::analyzeCV is deprecated. Use rule-based pipeline instead.');

        return ['deprecated' => true, 'message' => 'Use ParseCvJob + ScoreCvRuleBasedJob + ExplainCvAnalysisWithGeminiJob'];
    }

    /**
     * @deprecated Use ComputeMatchScoreJob + explainJobMatch instead
     */
    public function generateJobRecommendations(array $userProfile, array $availableJobs): array
    {
        Log::warning('GeminiAIService::generateJobRecommendations is deprecated. Use ComputeMatchScoreJob instead.');

        return ['deprecated' => true, 'message' => 'Use ComputeMatchScoreJob + ExplainMatchResultJob'];
    }

    /**
     * @deprecated Use ComputeCompatibilityJob + explainCompatibility instead
     */
    public function scoreCandidateForJob(array $candidateProfile, array $jobRequirements): array
    {
        Log::warning('GeminiAIService::scoreCandidateForJob is deprecated. Use ComputeCompatibilityJob instead.');

        return ['deprecated' => true, 'message' => 'Use ComputeCompatibilityJob + ExplainCompatibilityJob'];
    }

    /**
     * @deprecated Use ComputeSkillGapJob instead
     */
    public function suggestSkillGaps(array $currentSkills, array $targetRole): array
    {
        Log::warning('GeminiAIService::suggestSkillGaps is deprecated. Use ComputeSkillGapJob instead.');

        return ['deprecated' => true, 'message' => 'Use ComputeSkillGapJob'];
    }

    // =========================================================================
    // PROMPT BUILDERS (Explanation-Only - No Scoring)
    // =========================================================================

    private function buildCvExplanationPrompt(array $context): string
    {
        $contextJson = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are an intelligent CV analysis assistant. The CV has been analyzed and scores calculated.
Your task is to explain the results in clear, professional language.

PRE-CALCULATED DATA:
{$contextJson}

INSTRUCTIONS:
1. Identify the language of the CV (Arabic or English).
2. Provide the explanation in the SAME language as the CV.
3. If the CV is mixed, use English.
4. DO NOT provide multiple versions or nested language objects.
5. Provide simple, direct textual descriptions.
6. NO numeric scoring.
7. NO hiring decisions.

Return ONLY a JSON object with this exact structure:
{
    "strengths": "List the top 4 strengths in your resume compared to the market demands in your field. If there aren't 4 strengths, you can list fewer or even 0. Number the strengths 1-2-3-4 or fewer. The strengths should focus on the following: What skills does your resume have that are in high demand in your field? These skills should be based on your resume analysis. Do you have previous experience in your field? Are there any projects that could contribute to your employment? Are there any volunteer activities relevant to your field? Do you have any required foreign languages? Do you have any professional or internationally recognized certifications in your field? You can also mention strengths that are relevant to your field."
    "potential_gaps":  "List the top 3 skill gaps between your resume and the job requirements in your field. What is your current level in these skills? What is the job market's target level? The gap statement should be phrased as follows: You have a weakness in [skill type], and the job market needs this skill and expects you to be [skill type]. Able to (and mention the expected outputs and capabilities of someone with this skill). These are the required skills according to the resume analysis date.",
    "improvement_recommendations": "Mention the top 4 recommendations you want him to improve and number them 1-2-3-4. If you don't have any recommendations, that's fine, and if you have fewer, that's also fine. The recommendations should revolve around the following: What skills are in high demand in the market and in his field that he doesn't possess? These are the required skills according to the resume analysis date. Also, does he have a deficiency in filling out any important sections of his resume, or is it insufficient and unconvincing? Does he lack links to his projects or any way to view them? Does he have a significant lack of professional certifications in high demand in his field? Is he lacking in foreign languages ​​without which he wouldn't have found work? Is he lacking in volunteer work or other improvements needed in the job market?"
}
PROMPT;
    }

    private function buildCompatibilityExplanationPrompt(array $context): string
    {
        $contextJson = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
أنت مساعد ذكي لتفسير نتائج التوافق بين المرشح والوظيفة.
تم حساب درجات التوافق مسبقاً باستخدام خوارزميات قاعدية.

⚠️ قواعد صارمة:
- لا تعطي أي قرارات توظيف (مثل: توظيف، رفض، قوي، ضعيف)
- لا تقم بإعطاء درجات جديدة
- فقط اشرح لماذا مستوى التوافق هو كما هو

البيانات المحسوبة:
{$contextJson}

أعطني تفسيراً بتنسيق JSON:
{
    "explanation": "شرح واضح لسبب كون التوافق بهذا المستوى",
    "strengths": ["نقطة قوة 1", "نقطة قوة 2"],
    "gaps": ["فجوة 1", "فجوة 2"]
}
PROMPT;
    }

    private function buildMatchExplanationPrompt(array $matchData): string
    {
        $dataJson = json_encode($matchData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
أنت مساعد ذكي لتفسير نتائج مطابقة السيرة الذاتية مع الوظائف.
تم حساب درجات المطابقة مسبقاً.

⚠️ قاعدة: لا تعطي درجات جديدة، فقط اشرح النتائج.

البيانات:
{$dataJson}

أعطني تفسيراً بتنسيق JSON:
{
    "match_explanation": "لماذا هذه الوظيفة تتوافق مع المرشح",
    "key_matching_points": ["نقطة تطابق 1", "نقطة تطابق 2"],
    "improvement_areas": ["مجال تحسين 1"]
}
PROMPT;
    }

    private function buildCareerRoadmapPrompt(array $userProfile, string $targetRole): string
    {
        $profileJson = json_encode($userProfile, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
أنت مستشار مهني ذكي. ساعد المستخدم في إنشاء خارطة طريق للوصول إلى هدفه المهني.

⚠️ قواعد:
- لا تعطي درجات رقمية
- لا تقدم ضمانات
- قدم خطوات عملية واقعية

ملف المستخدم:
{$profileJson}

الهدف المهني: {$targetRole}

أعطني خارطة طريق بتنسيق JSON حصراً. لا تضف أي نص قبل أو بعد الـ JSON.
يجب أن يكون الرد عبارة عن كائن JSON صالح فقط:
{
    "current_level": "وصف المستوى الحالي",
    "target_level": "وصف المستوى المستهدف",
    "milestones": [
        {
            "title": "المرحلة 1",
            "duration": "الوقت المتوقع",
            "skills_to_learn": ["مهارة 1", "مهارة 2"],
            "actions": ["خطوة 1", "خطوة 2"]
        }
    ],
    "total_estimated_time": "الوقت الإجمالي التقريبي"
}
PROMPT;
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
            $response = Http::timeout(60)->withoutVerifying()->post($url, [
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

    private function parseExplanationResponse(string $response): array
    {
        return $this->parseJsonResponse($response);
    }

    private function parseCareerRoadmapResponse(string $response): array
    {
        return $this->parseJsonResponse($response);
    }

    private function parseJsonResponse(string $response): array
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

        // Step 3: Gemini often prepends free text before JSON — extract the JSON object
        $firstBrace = strpos($cleaned, '{');
        $lastBrace = strrpos($cleaned, '}');

        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $jsonCandidate = substr($cleaned, $firstBrace, $lastBrace - $firstBrace + 1);
            $decoded = json_decode($jsonCandidate, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        Log::warning('Failed to parse Gemini response as JSON', [
            'response_prefix' => substr($cleaned, 0, 100),
            'response_suffix' => substr($cleaned, -100),
            'full_length' => strlen($cleaned),
            'error' => json_last_error_msg(),
        ]);

        return ['raw_response' => $cleaned, 'parse_error' => true];
    }

    // =========================================================================
    // CACHING & TRACKING
    // =========================================================================

    /**
     * Generate hash for input to enable caching.
     */
    private function generateInputHash(array $input): string
    {
        return md5(json_encode($input) . self::PROMPT_VERSION);
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
