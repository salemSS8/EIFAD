<?php

namespace App\Domain\CV\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CVCertification Model
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
    ];

    protected function casts(): array
    {
        return [
            'IsVerified' => 'boolean',
            'IssueDate' => 'date',
        ];
    }

    /**
     * Get the CV this certification belongs to.
     */
    public function cv(): BelongsTo
    {
        return $this->belongsTo(CV::class, 'CVID', 'CVID');
    }
}
