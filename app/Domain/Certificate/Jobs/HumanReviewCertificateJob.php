<?php

namespace App\Domain\Certificate\Jobs;

use App\Domain\Certificate\Models\Certificate;
use App\Domain\Communication\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job: Queue certificate for human review.
 * 
 * This job handles certificates that cannot be automatically
 * verified and need manual review by an admin.
 * 
 * Creates an audit trail for the review process.
 */
class HumanReviewCertificateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public Certificate $certificate
    ) {}
    
    /**
     * Execute the job.
     */
    public function handle(): array
    {
        try {
            // Update certificate status
            $this->certificate->update([
                'VerificationStatus' => 'human_review',
                'UpdatedAt' => now(),
            ]);

            // Create review record for audit trail
            $reviewData = $this->createReviewRecord();

            // Notify admins (if notification system is set up)
            $this->notifyAdmins();

            Log::info('HumanReviewCertificateJob: Certificate queued for human review', [
                'certificate_id' => $this->certificate->CertificateID,
                'issuer' => $this->certificate->IssuerName,
            ]);

            return [
                'success' => true,
                'status' => 'queued_for_review',
                'review_data' => $reviewData,
            ];
        } catch (\Exception $e) {
            Log::error('HumanReviewCertificateJob failed', [
                'certificate_id' => $this->certificate->CertificateID,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create a review record for audit trail.
     */
    private function createReviewRecord(): array
    {
        return [
            'certificate_id' => $this->certificate->CertificateID,
            'certificate_name' => $this->certificate->CertificateName,
            'issuer_name' => $this->certificate->IssuerName,
            'credential_id' => $this->certificate->CredentialID,
            'credential_url' => $this->certificate->CredentialURL,
            'extracted_data' => $this->certificate->ExtractedData,
            'verifiability_level' => $this->certificate->VerifiabilityLevel,
            'previous_notes' => $this->certificate->VerificationNotes,
            'queued_at' => now()->toIso8601String(),
            'review_checklist' => [
                'verify_issuer_authenticity' => false,
                'verify_credential_id' => false,
                'verify_date_validity' => false,
                'verify_holder_name' => false,
            ],
        ];
    }

    /**
     * Notify admins about pending review.
     */
    private function notifyAdmins(): void
    {
        try {
            // Find admin users (role-based)
            $adminUserIds = \App\Domain\User\Models\UserRole::where('RoleID', function ($q) {
                $q->select('RoleID')
                    ->from('role')
                    ->where('RoleName', 'Admin')
                    ->limit(1);
            })->pluck('UserID');

            foreach ($adminUserIds as $adminId) {
                Notification::create([
                    'UserID' => $adminId,
                    'Type' => 'certificate_review',
                    'Title' => 'Certificate Pending Review',
                    'Content' => "Certificate '{$this->certificate->CertificateName}' needs manual verification.",
                    'Data' => json_encode([
                        'certificate_id' => $this->certificate->CertificateID,
                        'action_url' => "/admin/certificates/{$this->certificate->CertificateID}/review",
                    ]),
                    'IsRead' => false,
                    'CreatedAt' => now(),
                ]);
            }
        } catch (\Exception $e) {
            // Don't fail the job if notification fails
            Log::warning('Failed to notify admins', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Mark certificate as verified (to be called by admin).
     */
    public static function markAsVerified(Certificate $certificate, int $reviewerId, string $notes = null): void
    {
        $certificate->update([
            'VerificationStatus' => 'verified',
            'VerificationNotes' => $notes ?? 'Manually verified by admin',
            'VerifiedAt' => now(),
            'VerifiedBy' => $reviewerId,
            'UpdatedAt' => now(),
        ]);

        Log::info('Certificate manually verified', [
            'certificate_id' => $certificate->CertificateID,
            'reviewer_id' => $reviewerId,
        ]);
    }

    /**
     * Mark certificate as rejected (to be called by admin).
     */
    public static function markAsRejected(Certificate $certificate, int $reviewerId, string $reason): void
    {
        $certificate->update([
            'VerificationStatus' => 'rejected',
            'VerificationNotes' => "Rejected: {$reason}",
            'VerifiedAt' => now(),
            'VerifiedBy' => $reviewerId,
            'UpdatedAt' => now(),
        ]);

        Log::info('Certificate rejected', [
            'certificate_id' => $certificate->CertificateID,
            'reviewer_id' => $reviewerId,
            'reason' => $reason,
        ]);
    }
}
