<?php

namespace App\Domain\Communication\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Message Model - Matches `message` table in database.
 */
class Message extends Model
{
    protected $table = 'message';
    protected $primaryKey = 'MessageID';
    public $timestamps = false;

    protected $fillable = [
        'ConversationID',
        'SenderID',
        'Content',
        'SentAt',
        'IsDeleted',
    ];

    protected function casts(): array
    {
        return [
            'SentAt' => 'datetime',
            'IsDeleted' => 'boolean',
        ];
    }

    /**
     * Get the conversation this message belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'ConversationID', 'ConversationID');
    }

    /**
     * Get the sender.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'SenderID', 'UserID');
    }

    /**
     * Get read receipts.
     */
    public function readReceipts(): HasMany
    {
        return $this->hasMany(MessageRead::class, 'MessageID', 'MessageID');
    }
}
