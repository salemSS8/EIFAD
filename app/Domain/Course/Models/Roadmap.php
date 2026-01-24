<?php

namespace App\Domain\Course\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Roadmap Model - User career development roadmap.
 */
class Roadmap extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'target_role',
        'current_level',
        'target_level',
        'milestones',
        'total_estimated_time',
        'generated_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'milestones' => 'array',
            'generated_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the user this roadmap belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class);
    }
}
