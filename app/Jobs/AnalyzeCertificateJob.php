<?php

namespace App\Jobs;

use App\Ai\Agents\CertificateVerifier;
use App\Domain\Communication\Models\Notification;
use App\Domain\CV\Models\CVCertification;
use App\Domain\User\Models\UserRole;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AnalyzeCertificateJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public CVCertification $certification,
        public string $sourceType = 'job_seeker',
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Step 1: Extract text from file if available
            $extractedText = $this->extractContent();

            // Step 2: Update extraction info
            $this->certification->update([
                'ExtractedData' => ['raw_text' => $extractedText],
                'ExtractionMethod' => $extractedText ? 'pdf_parser' : 'manual',
                'ExtractedAt' => now(),
            ]);

            // Step 3: Call AI Agent
            $verifier = new CertificateVerifier(
                certificateName: $this->certification->CertificateName,
                issuingOrganization: $this->certification->IssuingOrganization,
                extractedText: $extractedText,
                sourceType: $this->sourceType,
                credentialId: $this->certification->CredentialID,
                credentialUrl: $this->certification->CredentialURL,
            );

            $aiResponse = $verifier->prompt('ابدأ تحليل الشهادة بناءً على التعليمات المعطاة لك.');

            // Convert StructuredAgentResponse to array
            $response = [
                'confidence_score' => $aiResponse['confidence_score'] ?? 0,
                'recommendation' => $aiResponse['recommendation'] ?? 'review',
                'issuer_known' => $aiResponse['issuer_known'] ?? false,
                'notes' => $aiResponse['notes'] ?? '',
                'extracted_info' => $aiResponse['extracted_info'] ?? [],
            ];

            // Step 4: Save AI results
            $this->certification->update([
                'AiConfidenceScore' => $response['confidence_score'] ?? 0,
                'AiModel' => 'gemini-2.5-flash',
                'VerificationStatus' => 'ai_reviewed',
                'VerificationNotes' => $response['notes'] ?? '',
                'ExtractedData' => array_merge(
                    $this->certification->ExtractedData ?? [],
                    ['ai_result' => $response]
                ),
            ]);

            Log::info('AnalyzeCertificateJob: AI analysis complete', [
                'certification_id' => $this->certification->CertificationID,
                'confidence' => $response['confidence_score'] ?? 0,
                'recommendation' => $response['recommendation'] ?? 'unknown',
            ]);

            // Step 5: Notify all admins
            $this->notifyAdmins($response);
        } catch (\Exception $e) {
            Log::error('AnalyzeCertificateJob failed', [
                'certification_id' => $this->certification->CertificationID,
                'error' => $e->getMessage(),
            ]);

            // Mark as pending so admin can still review manually
            $this->certification->update([
                'VerificationStatus' => 'pending',
                'VerificationNotes' => 'فشل التحليل الآلي: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Extract text content from the certificate file.
     */
    private function extractContent(): ?string
    {
        $filePath = $this->certification->FilePath;

        if (empty($filePath)) {
            return null;
        }

        $absolutePath = Storage::disk('local')->path($filePath);

        if (! file_exists($absolutePath)) {
            return null;
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        if ($extension === 'pdf') {
            try {
                $parser = new \Smalot\PdfParser\Parser;
                $pdf = $parser->parseFile($absolutePath);

                return $pdf->getText();
            } catch (\Exception $e) {
                Log::warning('AnalyzeCertificateJob: PDF parsing failed', [
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        }

        return null;
    }

    /**
     * Notify all admin users about the AI analysis result.
     */
    private function notifyAdmins(array $response): void
    {
        try {
            $recommendation = $response['recommendation'] ?? 'review';
            $confidence = $response['confidence_score'] ?? 0;
            $certName = $this->certification->CertificateName;

            $emoji = match ($recommendation) {
                'approve' => '✅',
                'reject' => '❌',
                default => '⚠️',
            };

            $content = "{$emoji} تحليل AI للشهادة \"{$certName}\": ثقة {$confidence}% - توصية: {$recommendation}. {$response['notes']}";

            $adminUserIds = UserRole::where('RoleID', function ($q) {
                $q->select('RoleID')
                    ->from('role')
                    ->where('RoleName', 'Admin')
                    ->limit(1);
            })->pluck('UserID');

            foreach ($adminUserIds as $adminId) {
                Notification::create([
                    'UserID' => $adminId,
                    'Type' => 'ai_alert',
                    'Content' => $content,
                    'IsRead' => false,
                    'CreatedAt' => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('AnalyzeCertificateJob: Failed to notify admins', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
