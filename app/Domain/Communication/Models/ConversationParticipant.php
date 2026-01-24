<?php

namespace App\Domain\Communication\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ConversationParticipant Model - Matches `conversationparticipant` table.
 */
class ConversationParticipant extends Model
{
    protected $table = 'conversationparticipant';
    protected $primaryKey = 'ParticipantID';
    public $timestamps = false;

    protected $fillable = [
        'ConversationID',
        'UserID',
        'JoinedAt',
        'IsMuted',
        'IsBlocked',
    ];

    protected function casts(): array
    {
        return [
            'JoinedAt' => 'datetime',
            'IsMuted' => 'boolean',
            'IsBlocked' => 'boolean',
        ];
    }

    /**
     * Get the conversation.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'ConversationID', 'ConversationID');
    }

    /**
     * Get the user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'UserID', 'UserID');
    }
}
