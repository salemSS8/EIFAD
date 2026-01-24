<?php

namespace App\Domain\Certificate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Certificate Model - Represents a user's certificate/credential.
 */
class Certificate extends Model
{
    protected $table = 'certificates';
    protected $primaryKey = 'CertificateID';
    public $timestamps = false;

    protected $fillable = [
        'CVID',
        'JobSeekerID',
        'CertificateName',
        'IssuerName',
        'IssuerID',
        'CredentialID',
        'CredentialURL',
        'IssueDate',
        'ExpiryDate',
        'FilePath',

        // Extraction Results
        'ExtractedData',
        'ExtractionMethod', // 'ocr', 'manual', 'api'
        'ExtractedAt',

        // Verification Status
        'VerificationStatus', // 'pending', 'auto_verified', 'human_review', 'verified', 'rejected', 'unverifiable'
        'VerifiabilityLevel', // 'auto', 'manual', 'insufficient_data'
        'VerificationNotes',
        'VerifiedAt',
        'VerifiedBy',

        'CreatedAt',
        'UpdatedAt',
    ];

    protected function casts(): array
    {
        return [
            'ExtractedData' => 'array',
            'IssueDate' => 'date',
            'ExpiryDate' => 'date',
            'ExtractedAt' => 'datetime',
            'VerifiedAt' => 'datetime',
            'CreatedAt' => 'datetime',
            'UpdatedAt' => 'datetime',
        ];
    }

    /**
     * Get the issuer from registry.
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(IssuerRegistry::class, 'IssuerID', 'IssuerID');
    }

    /**
     * Get the CV this certificate belongs to.
     */
    public function cv(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\CV\Models\CV::class, 'CVID', 'CVID');
    }

    /**
     * Check if certificate is verified.
     */
    public function isVerified(): bool
    {
        return in_array($this->VerificationStatus, ['auto_verified', 'verified']);
    }

    /**
     * Check if certificate needs human review.
     */
    public function needsHumanReview(): bool
    {
        return $this->VerificationStatus === 'human_review';
    }
}
