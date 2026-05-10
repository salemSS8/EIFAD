<?php

namespace App\Domain\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileViewDevice extends Model
{
    protected $table = 'profile_view_devices';

    protected $fillable = [
        'viewed_user_id',
        'device_hash',
        'viewer_id',
        'ip_address',
        'user_agent',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    /**
     * The user whose profile was viewed.
     */
    public function viewedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewed_user_id', 'UserID');
    }

    /**
     * The user who viewed the profile (if logged in).
     */
    public function viewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewer_id', 'UserID');
    }
}
