<?php

namespace App\Domain\Communication\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conversation Model - Matches `conversation` table in database.
 */
class Conversation extends Model
{
    protected $table = 'conversation';
    protected $primaryKey = 'ConversationID';
    public $timestamps = false;

    protected $fillable = [
        'Type',
        'CreatedAt',
    ];

    protected function casts(): array
    {
        return [
            'CreatedAt' => 'datetime',
        ];
    }

    /**
     * Get participants in this conversation.
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class, 'ConversationID', 'ConversationID');
    }

    /**
     * Get messages in this conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'ConversationID', 'ConversationID');
    }
}
