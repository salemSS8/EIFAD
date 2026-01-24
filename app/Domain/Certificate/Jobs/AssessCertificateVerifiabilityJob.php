<?php

namespace App\Domain\Certificate\Jobs;

use App\Domain\Certificate\Models\Certificate;
use App\Domain\Certificate\Models\IssuerRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job: Assess certificate verifiability.
 * 
 * Determines if a certificate can be:
 * - Automatically verified (via API)
 * - Needs human review
 * - Has insufficient data
 * 
 * NO AI decisions - rule-based assessment.
 */
class AssessCertificateVerifiabilityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public Certificate $certificate
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): array
    {
        try {
            // Load extracted data
            $extractedData = $this->certificate->ExtractedData ?? [];
            $issuerName = $this->certificate->IssuerName ?? $extractedData['issuer_name'] ?? null;

            // Step 1: Lookup issuer in registry
            $issuer = null;
            if ($issuerName) {
                $issuer = IssuerRegistry::findByNameOrDomain($issuerName);
            }

            // Step 2: Assess verifiability
            $assessment = $this->assessVerifiability($issuer, $extractedData);

            // Step 3: Update certificate
            $this->certificate->update([
                'IssuerID' => $issuer?->IssuerID,
                'VerifiabilityLevel' => $assessment['level'],
                'VerificationStatus' => $assessment['next_status'],
                'VerificationNotes' => $assessment['notes'],
                'UpdatedAt' => now(),
            ]);

            Log::info('AssessCertificateVerifiabilityJob: Assessment complete', [
                'certificate_id' => $this->certificate->CertificateID,
                'level' => $assessment['level'],
                'next_status' => $assessment['next_status'],
            ]);

            // Step 4: Dispatch appropriate next job
            $this->dispatchNextJob($assessment);

            return [
                'success' => true,
                'assessment' => $assessment,
            ];
        } catch (\Exception $e) {
            Log::error('AssessCertificateVerifiabilityJob failed', [
                'certificate_id' => $this->certificate->CertificateID,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Assess verifiability based on issuer and data.
     */
    private function assessVerifiability(?IssuerRegistry $issuer, array $extractedData): array
    {
        $hasCredentialId = !empty($this->certificate->CredentialID) || !empty($extractedData['credential_id']);
        $hasCredentialUrl = !empty($this->certificate->CredentialURL);
        $hasIssuer = !empty($issuer);

        // Case 1: Known issuer with API verification
        if ($hasIssuer && $issuer->supportsAutoVerification() && $hasCredentialId) {
            return [
                'level' => 'auto',
                'next_status' => 'pending',
                'notes' => 'Issuer supports automatic verification',
                'action' => 'auto_verify',
            ];
        }

        // Case 2: Has verification URL
        if ($hasCredentialUrl) {
            return [
                'level' => 'manual',
                'next_status' => 'human_review',
                'notes' => 'Has credential URL - needs manual verification',
                'action' => 'human_review',
            ];
        }

        // Case 3: Known issuer but needs manual check
        if ($hasIssuer && !$issuer->supportsAutoVerification()) {
            return [
                'level' => 'manual',
                'next_status' => 'human_review',
                'notes' => 'Known issuer but no API - needs manual verification',
                'action' => 'human_review',
            ];
        }

        // Case 4: Has credential ID but unknown issuer
        if ($hasCredentialId && !$hasIssuer) {
            return [
                'level' => 'manual',
                'next_status' => 'human_review',
                'notes' => 'Unknown issuer - needs manual verification',
                'action' => 'human_review',
            ];
        }

        // Case 5: Insufficient data
        return [
            'level' => 'insufficient_data',
            'next_status' => 'unverifiable',
            'notes' => 'Insufficient data for verification',
            'action' => 'none',
        ];
    }

    /**
     * Dispatch the appropriate next job.
     */
    private function dispatchNextJob(array $assessment): void
    {
        switch ($assessment['action']) {
            case 'auto_verify':
                AutoVerifyCertificateJob::dispatch($this->certificate->fresh())
                    ->delay(now()->addSeconds(5));
                break;

            case 'human_review':
                HumanReviewCertificateJob::dispatch($this->certificate->fresh())
                    ->delay(now()->addSeconds(5));
                break;

            default:
                // No further action needed
                break;
        }
    }
}
