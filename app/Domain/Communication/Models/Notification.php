<?php

namespace App\Domain\Communication\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notification Model - Matches `notification` table in database.
 */
class Notification extends Model
{
    protected $table = 'notification';

    protected $primaryKey = 'NotificationID';

    public $timestamps = false;

    protected $fillable = [
        'UserID',
        'Type',
        'Content',
        'IsRead',
        'CreatedAt',
    ];

    protected function casts(): array
    {
        return [
            'IsRead' => 'boolean',
            'CreatedAt' => 'datetime',
        ];
    }

    /**
     * Get the user this notification belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'UserID', 'UserID');
    }
}
