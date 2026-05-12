<?php

namespace App\Domain\CV\Models;

use App\Domain\Certificate\Models\IssuerRegistry;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CVCertification Model — includes AI verification fields.
 */
class CVCertification extends Model
{
    protected $table = 'cv_certifications';

    protected $primaryKey = 'CertificationID';

    protected $fillable = [
        'CVID',
        'CertificateName',
        'IssuingOrganization',
        'IsVerified',
        'IssueDate',

        // Verification fields
        'FilePath',
        'CredentialID',
        'CredentialURL',
        'ExtractedData',
        'ExtractionMethod',
        'ExtractedAt',
        'VerificationStatus',
        'VerificationNotes',
        'AiConfidenceScore',
        'AiModel',
        'VerifiedAt',
        'VerifiedBy',
    ];

    protected function casts(): array
    {
        return [
            'IsVerified' => 'boolean',
            'IssueDate' => 'date',
            'ExtractedData' => 'array',
            'AiConfidenceScore' => 'float',
            'ExtractedAt' => 'datetime',
            'VerifiedAt' => 'datetime',
        ];
    }

    /**
     * Get the CV this certification belongs to.
     */
    public function cv(): BelongsTo
    {
        return $this->belongsTo(CV::class, 'CVID', 'CVID');
    }

    /**
     * Get the admin who verified this certificate.
     */
    public function verifiedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'VerifiedBy', 'UserID');
    }

    /**
     * Get the matched issuer from registry.
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(IssuerRegistry::class, 'IssuingOrganization', 'IssuerName');
    }

    /**
     * Check if certificate is pending review.
     */
    public function isPending(): bool
    {
        return $this->VerificationStatus === 'pending';
    }

    /**
     * Check if certificate has been reviewed by AI.
     */
    public function isAiReviewed(): bool
    {
        return $this->VerificationStatus === 'ai_reviewed';
    }

    /**
     * Check if certificate is verified.
     */
    public function isVerifiedStatus(): bool
    {
        return $this->VerificationStatus === 'verified';
    }

    /**
     * Check if certificate is rejected.
     */
    public function isRejected(): bool
    {
        return $this->VerificationStatus === 'rejected';
    }
}
