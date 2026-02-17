<?php

namespace App\Domain\Communication\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ChatPermission Model - Tracks who can chat with whom.
 * Chat permissions logic.
 */
class ChatPermission extends Model
{
    protected $fillable = [
        'user_id',
        'target_user_id',
        'job_id',
        'application_id',
        'permission_type',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the user who has permission.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class);
    }

    /**
     * Get the target user they can chat with.
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'target_user_id');
    }

    /**
     * Get the related job (if applicable).
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Job\Models\JobAd::class, 'job_id', 'JobAdID');
    }
}
