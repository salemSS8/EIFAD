<?php

namespace App\Domain\CV\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CVCustomSection extends Model
{
    use HasFactory;

    protected $table = 'cv_custom_sections';

    protected $primaryKey = 'CustomSectionID';

    protected $fillable = [
        'CVID',
        'SectionType',
        'Title',
        'Description',
        'StartDate',
        'EndDate',
    ];

    public function cv(): BelongsTo
    {
        return $this->belongsTo(CV::class, 'CVID', 'CVID');
    }
}
