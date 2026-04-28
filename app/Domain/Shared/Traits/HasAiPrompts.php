<?php

namespace App\Domain\Shared\Traits;

trait HasAiPrompts
{
    protected function buildCvExplanationPrompt(array $context): string
    {
        $contextJson = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are an intelligent CV analysis assistant. The CV has been analyzed and scores calculated.
Your task is to explain the results in clear, professional language.

PRE-CALCULATED DATA:
{$contextJson}

INSTRUCTIONS:
1. Provide the analysis in BOTH Arabic and English.
2. Structure the response into three categories: "strengths", "weaknesses", and "gaps".
3. Each category must be an ARRAY of objects, where each object contains an "en" (English) and "ar" (Arabic) version of the point.
4. ALL points in ALL categories must be NUMBERED (1., 2., 3., etc.).
5. NO numeric scoring or hiring decisions.
6. Return ONLY a valid JSON object.
7. strengths: List the top 4 strengths in your resume compared to the market demands in your field. If there aren't 4 strengths, you can list fewer or even 0. Number the strengths 1-2-3-4 or fewer. The strengths should focus on the following: What skills does your resume have that are in high demand in your field? These skills should be based on your resume analysis. Do you have previous experience in your field? Are there any projects that could contribute to your employment? Are there any volunteer activities relevant to your field? Do you have any required foreign languages? Do you have any professional or internationally recognized certifications in your field? You can also mention strengths that are relevant to your field.
8.potential_gaps: List the top 3 skill gaps between your resume and the job requirements in your field. What is your current level in these skills? What is the job market's target level? The gap statement should be phrased as follows: You have a weakness in [skill type], and the job market needs this skill and expects you to be [skill type]. Able to (and mention the expected outputs and capabilities of someone with this skill). These are the required skills according to the resume analysis date.
9.improvement_recommendations: Mention the top 4 recommendations you want him to improve and number them 1-2-3-4. If you don't have any recommendations, that's fine, and if you have fewer, that's also fine. The recommendations should revolve around the following: What skills are in high demand in the market and in his field that he doesn't possess? These are the required skills according to the resume analysis date. Also, does he have a deficiency in filling out any important sections of his resume, or is it insufficient and unconvincing? Does he lack links to his projects or any way to view them? Does he have a significant lack of professional certifications in high demand in his field? Is he lacking in foreign languages ​​without which he wouldn't have found work? Is he lacking in volunteer work or other improvements needed in the job market?


EXPECTED JSON STRUCTURE:
{
    "strengths": [
        {"en": "1. [English Strength 1]", "ar": "1. [Arabic Strength 1]"},
        {"en": "2. [English Strength 2]", "ar": "2. [Arabic Strength 2]"}
    ],
    "weaknesses": [
        {"en": "1. [English Weakness 1]", "ar": "1. [Arabic Weakness 1]"}
    ],
    "gaps": [
        {"en": "1. [English Gap 1]", "ar": "1. [Arabic Gap 1]"}
    ],
    "recommendations": [
        {"en": "1. [English Recommendation 1]", "ar": "1. [Arabic Recommendation 1]"}
    ]
}
PROMPT;
    }

    protected function buildCompatibilityExplanationPrompt(array $context): string
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

    protected function buildMatchExplanationPrompt(array $matchData): string
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

    protected function buildCareerRoadmapPrompt(array $userProfile, string $targetRole): string
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
}
