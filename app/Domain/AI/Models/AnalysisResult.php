<?php

namespace App\Domain\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AnalysisResult Model - Matches `analysisresult` table in database.
 * Generic AI analysis result storage.
 */
class AnalysisResult extends Model
{
    protected $table = 'analysisresult';
    protected $primaryKey = 'AnalysisID';
    public $timestamps = false;

    protected $fillable = [
        'TargetType',
        'TargetID',
        'UserID',
        'ResultText',
        'Score',
        'ModelVersion',
        'CreatedAt',
    ];

    protected function casts(): array
    {
        return [
            'CreatedAt' => 'datetime',
            'Score' => 'integer',
        ];
    }

    /**
     * Get the user this analysis was performed for.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'UserID', 'UserID');
    }
}
