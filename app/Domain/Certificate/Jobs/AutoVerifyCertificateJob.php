<?php

namespace App\Domain\Certificate\Jobs;

use App\Domain\Certificate\Models\Certificate;
use App\Domain\Certificate\Models\IssuerRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Job: Automatically verify certificate via issuer API.
 * 
 * This job attempts to verify a certificate using the
 * issuer's official verification API.
 * 
 * NO AI - uses issuer's verification endpoint.
 */
class AutoVerifyCertificateJob implements ShouldQueue
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
            $this->certificate->load('issuer');
            $issuer = $this->certificate->issuer;

            if (!$issuer || !$issuer->supportsAutoVerification()) {
                Log::warning('AutoVerifyCertificateJob: No auto-verification available', [
                    'certificate_id' => $this->certificate->CertificateID
                ]);

                // Escalate to human review
                $this->certificate->update([
                    'VerificationStatus' => 'human_review',
                    'VerificationNotes' => 'Auto-verification not available, escalated to human review',
                ]);

                HumanReviewCertificateJob::dispatch($this->certificate->fresh());

                return ['success' => false, 'reason' => 'No auto-verification available'];
            }

            // Attempt verification
            $result = $this->verifyWithIssuer($issuer);

            if ($result['verified']) {
                $this->certificate->update([
                    'VerificationStatus' => 'auto_verified',
                    'VerificationNotes' => 'Automatically verified via issuer API',
                    'VerifiedAt' => now(),
                    'VerifiedBy' => 'system',
                    'UpdatedAt' => now(),
                ]);

                Log::info('AutoVerifyCertificateJob: Certificate verified', [
                    'certificate_id' => $this->certificate->CertificateID
                ]);

                return ['success' => true, 'verified' => true];
            } else {
                // Verification failed - escalate to human review
                $this->certificate->update([
                    'VerificationStatus' => 'human_review',
                    'VerificationNotes' => 'Auto-verification failed: ' . ($result['reason'] ?? 'Unknown'),
                    'UpdatedAt' => now(),
                ]);

                HumanReviewCertificateJob::dispatch($this->certificate->fresh());

                return ['success' => true, 'verified' => false, 'reason' => $result['reason']];
            }
        } catch (\Exception $e) {
            Log::error('AutoVerifyCertificateJob failed', [
                'certificate_id' => $this->certificate->CertificateID,
                'error' => $e->getMessage()
            ]);

            // On error, escalate to human review
            $this->certificate->update([
                'VerificationStatus' => 'human_review',
                'VerificationNotes' => 'Auto-verification error: ' . $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Verify certificate with issuer's API.
     */
    private function verifyWithIssuer(IssuerRegistry $issuer): array
    {
        $apiUrl = $issuer->VerificationApiUrl;
        $credentialId = $this->certificate->CredentialID
            ?? $this->certificate->ExtractedData['credential_id']
            ?? null;

        if (empty($credentialId)) {
            return ['verified' => false, 'reason' => 'No credential ID'];
        }

        try {
            // Build verification request based on issuer
            $response = Http::timeout(30)->get($apiUrl, [
                'credential_id' => $credentialId,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Check for verification in response
                $isValid = $data['valid'] ?? $data['verified'] ?? $data['status'] === 'valid' ?? false;

                return [
                    'verified' => $isValid,
                    'reason' => $isValid ? 'Verified' : 'Not found or invalid',
                    'response' => $data,
                ];
            }

            return ['verified' => false, 'reason' => 'API request failed: ' . $response->status()];
        } catch (\Exception $e) {
            return ['verified' => false, 'reason' => 'API error: ' . $e->getMessage()];
        }
    }
}
