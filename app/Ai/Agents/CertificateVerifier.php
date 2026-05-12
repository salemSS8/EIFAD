<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * AI Agent: Certificate Verifier.
 *
 * Analyzes certificate data and determines authenticity confidence.
 */
#[Provider(Lab::Gemini)]
#[Model('gemini-2.5-flash')]
class CertificateVerifier implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        protected string $certificateName,
        protected ?string $issuingOrganization,
        protected ?string $extractedText,
        protected string $sourceType,
        protected ?string $credentialId = null,
        protected ?string $credentialUrl = null,
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $certDetails = json_encode([
            'certificate_name' => $this->certificateName,
            'issuing_organization' => $this->issuingOrganization,
            'source_type' => $this->sourceType,
            'credential_id' => $this->credentialId,
            'credential_url' => $this->credentialUrl,
            'extracted_text' => $this->extractedText,
        ], JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
        أنت خبير تحقق من الشهادات المهنية والأكاديمية. مهمتك هي تحليل بيانات الشهادة المقدمة وتقييم مدى مصداقيتها.

        بيانات الشهادة:
        ==============
        {$certDetails}

        قم بتحليل الشهادة بناءً على المعايير التالية:
        1. هل اسم الشهادة واضح ومحدد؟ (شهادات بأسماء عامة جداً مثل "شهادة" بدون تفاصيل مشبوهة)
        2. هل الجهة المصدرة معروفة وموثوقة؟ (مثل Google, Microsoft, Coursera, Udemy, PMI, إلخ)
        3. هل يوجد رقم اعتماد (Credential ID) أو رابط تحقق (Credential URL)؟
        4. إذا تم استخراج نص من الملف، هل المحتوى متسق ومنطقي؟
        5. هل التواريخ المذكورة منطقية؟

        المطلوب:
        - confidence_score: نسبة الثقة من 0 إلى 100
        - recommendation: توصية (approve = مقبولة, review = تحتاج مراجعة بشرية, reject = مرفوضة)
        - issuer_known: هل الجهة المصدرة معروفة
        - notes: ملاحظات تفصيلية للأدمن باللغة العربية
        - extracted_info: معلومات مستخرجة (اسم الشهادة، الجهة، التاريخ، رقم الاعتماد)
        PROMPT;
    }

    /**
     * Get the structured output schema.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'confidence_score' => $schema->integer()->description('نسبة الثقة من 0 إلى 100')->required(),
            'recommendation' => $schema->string()->description('التوصية: approve أو review أو reject')->required(),
            'issuer_known' => $schema->boolean()->description('هل الجهة المصدرة معروفة')->required(),
            'notes' => $schema->string()->description('ملاحظات تفصيلية للأدمن')->required(),
            'extracted_info' => $schema->object(fn ($schema) => [
                'name' => $schema->string()->description('اسم الشهادة المستخرج'),
                'issuer' => $schema->string()->description('الجهة المصدرة المستخرجة'),
                'date' => $schema->string()->description('تاريخ الإصدار المستخرج'),
                'credential_id' => $schema->string()->description('رقم الاعتماد المستخرج'),
            ])->description('معلومات مستخرجة من الشهادة')->required(),
        ];
    }
}
