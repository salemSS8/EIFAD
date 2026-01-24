<?php

namespace App\Domain\Communication\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MessageRead Model - Matches `messageread` table in database.
 */
class MessageRead extends Model
{
    protected $table = 'messageread';
    protected $primaryKey = 'ReadID';
    public $timestamps = false;

    protected $fillable = [
        'MessageID',
        'UserID',
        'ReadAt',
    ];

    protected function casts(): array
    {
        return [
            'ReadAt' => 'datetime',
        ];
    }

    /**
     * Get the message.
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'MessageID', 'MessageID');
    }

    /**
     * Get the user who read the message.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'UserID', 'UserID');
    }
}
