<?php

namespace App\Domain\Certificate\Jobs;

use App\Domain\Certificate\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Job: Extract data from certificate using OCR.
 * 
 * This job extracts structured data from certificate files:
 * - Certificate name
 * - Issuer name
 * - Credential ID
 * - Issue date
 * - Expiry date
 * 
 * NO AI decisions - pure extraction.
 */
class ExtractCertificateDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public Certificate $certificate
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): array
    {
        try {
            $filePath = $this->getFullFilePath();
            $extractedData = [];
            $extractionMethod = 'manual';

            if ($filePath && file_exists($filePath)) {
                // Try OCR extraction
                $extractedData = $this->extractWithOCR($filePath);
                $extractionMethod = 'ocr';
            }

            // Parse extracted text
            $parsedData = $this->parseExtractedText($extractedData['text'] ?? '');

            // Update certificate with extracted data
            $this->certificate->update([
                'ExtractedData' => array_merge($extractedData, $parsedData),
                'ExtractionMethod' => $extractionMethod,
                'ExtractedAt' => now(),
                'UpdatedAt' => now(),
            ]);

            Log::info('ExtractCertificateDataJob: Extraction complete', [
                'certificate_id' => $this->certificate->CertificateID,
                'method' => $extractionMethod,
            ]);

            // Dispatch verifiability assessment
            AssessCertificateVerifiabilityJob::dispatch($this->certificate->fresh());

            return [
                'success' => true,
                'extracted_data' => $parsedData,
            ];
        } catch (\Exception $e) {
            Log::error('ExtractCertificateDataJob failed', [
                'certificate_id' => $this->certificate->CertificateID,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get full file path.
     */
    private function getFullFilePath(): ?string
    {
        $filePath = $this->certificate->FilePath;
        return $filePath ? Storage::disk('local')->path($filePath) : null;
    }

    /**
     * Extract text using OCR (placeholder - integrate with real OCR service).
     */
    private function extractWithOCR(string $filePath): array
    {
        // In production, integrate with:
        // - Google Cloud Vision
        // - AWS Textract
        // - Tesseract OCR

        // For now, return placeholder for files we can read
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'pdf') {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($filePath);
                return ['text' => $pdf->getText(), 'method' => 'pdf_parser'];
            } catch (\Exception $e) {
                return ['text' => '', 'error' => $e->getMessage()];
            }
        }

        return ['text' => '', 'method' => 'unsupported'];
    }

    /**
     * Parse extracted text for certificate details.
     */
    private function parseExtractedText(string $text): array
    {
        $parsed = [
            'certificate_name' => null,
            'issuer_name' => null,
            'credential_id' => null,
            'issue_date' => null,
            'expiry_date' => null,
        ];

        if (empty($text)) {
            return $parsed;
        }

        $textLower = strtolower($text);

        // Extract credential ID patterns
        $credentialPatterns = [
            '/credential\s*(?:id|#)?[:\s]*([A-Z0-9\-]+)/i',
            '/certificate\s*(?:id|#|number)?[:\s]*([A-Z0-9\-]+)/i',
            '/id[:\s]*([A-Z0-9\-]{8,})/i',
        ];

        foreach ($credentialPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $parsed['credential_id'] = trim($matches[1]);
                break;
            }
        }

        // Extract dates
        $datePattern = '/(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}|\d{4}[\/\-\.]\d{1,2}[\/\-\.]\d{1,2})/';
        preg_match_all($datePattern, $text, $dates);

        if (!empty($dates[0])) {
            $parsed['issue_date'] = $dates[0][0] ?? null;
            $parsed['expiry_date'] = $dates[0][1] ?? null;
        }

        // Known issuers detection
        $knownIssuers = [
            'coursera',
            'udemy',
            'linkedin',
            'google',
            'microsoft',
            'aws',
            'cisco',
            'oracle',
            'pmp',
            'comptia',
            'salesforce'
        ];

        foreach ($knownIssuers as $issuer) {
            if (str_contains($textLower, $issuer)) {
                $parsed['issuer_name'] = ucfirst($issuer);
                break;
            }
        }

        return $parsed;
    }
}
