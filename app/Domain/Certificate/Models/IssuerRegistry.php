<?php

namespace App\Domain\Certificate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * IssuerRegistry Model - Registry of known certificate issuers.
 * 
 * Used to determine if a certificate can be automatically verified
 * or needs human review.
 */
class IssuerRegistry extends Model
{
    protected $table = 'issuer_registry';
    protected $primaryKey = 'IssuerID';
    public $timestamps = false;

    protected $fillable = [
        'IssuerName',
        'IssuerDomain',
        'VerificationApiUrl',
        'VerificationMethod', // 'api', 'manual', 'none'
        'IsVerifiable',
        'RequiresHumanReview',
        'CredentialPattern',
        'CreatedAt',
        'UpdatedAt',
    ];

    protected function casts(): array
    {
        return [
            'IsVerifiable' => 'boolean',
            'RequiresHumanReview' => 'boolean',
            'CreatedAt' => 'datetime',
            'UpdatedAt' => 'datetime',
        ];
    }

    /**
     * Check if issuer supports automatic verification.
     */
    public function supportsAutoVerification(): bool
    {
        return $this->IsVerifiable &&
            $this->VerificationMethod === 'api' &&
            !empty($this->VerificationApiUrl);
    }

    /**
     * Find issuer by name or domain.
     */
    public static function findByNameOrDomain(string $search): ?self
    {
        $search = strtolower(trim($search));

        return static::where(function ($q) use ($search) {
            $q->whereRaw('LOWER(IssuerName) LIKE ?', ["%{$search}%"])
                ->orWhereRaw('LOWER(IssuerDomain) LIKE ?', ["%{$search}%"]);
        })->first();
    }
}
