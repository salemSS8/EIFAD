<?php

namespace App\Ai\Agents;

use App\Domain\CV\Models\CV;
use App\Domain\Job\Models\JobAd;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
// إضافة مسارات الحزمة الخاصة بتحديد مزود الذكاء الاصطناعي
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

// توجيه الوكيل لاستخدام Gemini بناءً على ملف .env
#[Provider(Lab::Gemini)]
#[Model('gemini-2.5-flash')]
class ApplicantScreener implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Create a new agent instance.
     */
    public function __construct(
        protected JobAd $jobAd,
        protected CV $cv
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $jobDetails = json_encode([
            'Title' => $this->jobAd->Title,
            'Description' => $this->jobAd->Description,
            'Responsibilities' => $this->jobAd->Responsibilities,
            'Requirements' => $this->jobAd->Requirements,
        ], JSON_UNESCAPED_UNICODE);

        // تم إصلاح طريقة جلب العلاقات لتكون مصفوفة نصوص نظيفة وخفيفة
        $cvDetails = json_encode([
            'Title' => $this->cv->Title,
            'PersonalSummary' => $this->cv->PersonalSummary,
            'Skills' => $this->cv->skills()->with('skill')->get()->map(fn ($s) => $s->skill->SkillName ?? '')->toArray(),
            'Experience' => $this->cv->experiences->map(fn ($e) => "{$e->JobTitle} at {$e->CompanyName} ({$e->StartDate} to {$e->EndDate}): {$e->Responsibilities}")->toArray(),
            'Education' => $this->cv->education->map(fn ($e) => "{$e->DegreeName} in {$e->Major} from {$e->Institution}")->toArray(),
            'Languages' => $this->cv->languages()->with('language')->get()->map(fn ($l) => ($l->language->LanguageName ?? '')." ({$l->LanguageLevel})")->toArray(),
        ], JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
        أنت خبير توظيف ذكي (Expert HR Recruiter). مهمتك هي تحليل السيرة الذاتية (CV) للمتقدم ومقارنتها بدقة مع متطلبات الوظيفة (Job Advertisement) المعروضة.

        تفاصيل الوظيفة:
        =============
        {$jobDetails}

        السيرة الذاتية للمتقدم:
        ======================
        {$cvDetails}

        قم بتحليل مهارات المتقدم، خبراته، وتعليمه وقارنها مع متطلبات الوظيفة ومسؤولياتها.
        المطلوب منك إرجاع البيانات التالية بصيغة JSON المهيكلة فقط:
        1. `match_score`: نسبة التوافق المئوية كرقم صحيح من 0 إلى 100.
        2. `missing_skills`: مصفوفة بالمهارات التقنية والشخصية التي تتطلبها الوظيفة ويفتقر لها المتقدم.
        3. `notes`: تحليل قصير وتوصية عملية لصاحب العمل (هل يستحق المقابلة؟ ما نقاط قوته وضعفه باختصار؟).
        PROMPT;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'match_score' => $schema->integer()->description('نسبة التوافق المئوية من 0 إلى 100')->required(),
            'missing_skills' => $schema->array()->items(
                $schema->string()->description('اسم المهارة المفقودة')
            )->description('المهارات المطلوبة للوظيفة وغير الموجودة في السيرة الذاتية')->required(),
            'notes' => $schema->string()->description('تحليل قصير وتوصية عملية لصاحب العمل')->required(),
        ];
    }
}
