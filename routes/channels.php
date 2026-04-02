<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->UserID === (int) $id;
});

Broadcast::channel('conversation.{id}', function ($user, $id) {
    return \App\Domain\Communication\Models\ConversationParticipant::where('ConversationID', $id)
        ->where('UserID', $user->UserID)
        ->exists();
});

Broadcast::channel('company.notifications.{id}', function ($user, $id) {
    return (int) $user->UserID === (int) $id;
});
