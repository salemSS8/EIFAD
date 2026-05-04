<?php

namespace App\Http\Controllers\Api;

use App\Domain\Communication\Models\Conversation;
use App\Domain\Communication\Models\ConversationParticipant;
use App\Domain\Communication\Models\Message;
use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

/**
 * Chat Controller - Manages real-time conversations and messages.
 */
class ChatController extends Controller
{
    /**
     * Get user conversations.
     */
    #[OA\Get(
        path: '/conversations',
        operationId: 'getConversations',
        tags: ['Chat'],
        summary: 'List all conversations',
        description: 'Returns a paginated list of conversations the authenticated user is participating in.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Response(response: 200, description: 'List of conversations')]
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->UserID;

        $conversations = Conversation::whereHas('participants', function ($q) use ($userId) {
            $q->where('UserID', $userId);
        })
            ->with(['participants.user:UserID,FullName', 'messages' => function ($q) {
                $q->latest('SentAt')->limit(1);
            }])
            ->orderByDesc(function ($query) {
                $query->select('SentAt')
                    ->from('message')
                    ->whereColumn('message.ConversationID', 'conversation.ConversationID')
                    ->latest('SentAt')
                    ->limit(1);
            })
            ->paginate(20);

        return response()->json($conversations);
    }

    /**
     * Get messages for a specific conversation.
     */
    #[OA\Get(
        path: '/conversations/{id}/messages',
        operationId: 'getConversationMessages',
        tags: ['Chat'],
        summary: 'Get messages in a conversation',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'List of messages')]
    public function messages(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->UserID;

        // Verify user is participant
        $isParticipant = ConversationParticipant::where('ConversationID', $id)
            ->where('UserID', $userId)
            ->exists();

        if (! $isParticipant) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $messages = Message::with('sender:UserID,FullName')
            ->where('ConversationID', $id)
            ->where('IsDeleted', false)
            ->orderByDesc('SentAt')
            ->paginate(50);

        return response()->json($messages);
    }

    /**
     * Send a message to a conversation.
     */
    #[OA\Post(
        path: '/conversations/{id}/messages',
        operationId: 'sendMessage',
        tags: ['Chat'],
        summary: 'Send a message',
        description: 'Sends a message and broadcasts it via Laravel Reverb on channel `private-conversation.{id}`.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'content', type: 'string', maxLength: 5000),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Message sent')]
    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $request->validate(['content' => 'required|string|max:5000']);

        $userId = $request->user()->UserID;

        // Verify user is participant
        $isParticipant = ConversationParticipant::where('ConversationID', $id)
            ->where('UserID', $userId)
            ->exists();

        if (! $isParticipant) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $message = Message::create([
            'ConversationID' => $id,
            'SenderID' => $userId,
            'Content' => $request->input('content'),
            'SentAt' => now(),
            'IsDeleted' => false,
        ]);

        $message->load('sender:UserID,FullName');

        // Broadcast for real-time
        broadcast(new MessageSent($message))->toOthers();

        return response()->json([
            'message' => 'Message sent',
            'data' => $message,
        ], 201);
    }

    /**
     * Start a new conversation with another user.
     */
    #[OA\Post(
        path: '/conversations',
        operationId: 'startConversation',
        tags: ['Chat'],
        summary: 'Start a new conversation',
        description: 'Creates a new private conversation with another user.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'user_id', type: 'integer', description: 'The ID of the user to chat with'),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Conversation started')]
    public function startConversation(Request $request): JsonResponse
    {
        $request->validate(['user_id' => 'required|integer|exists:user,UserID']);

        $currentUserId = $request->user()->UserID;
        $otherUserId = $request->input('user_id');

        if ($currentUserId == $otherUserId) {
            return response()->json(['message' => 'Cannot start a conversation with yourself'], 422);
        }

        // Check if a private conversation already exists between these two users
        $existingConversationId = DB::table('conversationparticipant as p1')
            ->join('conversationparticipant as p2', 'p1.ConversationID', '=', 'p2.ConversationID')
            ->join('conversation as c', 'p1.ConversationID', '=', 'c.ConversationID')
            ->where('c.Type', 'Private')
            ->where('p1.UserID', $currentUserId)
            ->where('p2.UserID', $otherUserId)
            ->value('p1.ConversationID');

        if ($existingConversationId) {
            $conversation = Conversation::with('participants.user:UserID,FullName')->find($existingConversationId);

            return response()->json(['data' => $conversation]);
        }

        return DB::transaction(function () use ($currentUserId, $otherUserId) {
            $conversation = Conversation::create([
                'Type' => 'Private',
                'CreatedAt' => now(),
            ]);

            ConversationParticipant::create([
                'ConversationID' => $conversation->ConversationID,
                'UserID' => $currentUserId,
                'JoinedAt' => now(),
            ]);

            ConversationParticipant::create([
                'ConversationID' => $conversation->ConversationID,
                'UserID' => $otherUserId,
                'JoinedAt' => now(),
            ]);

            $conversation->load('participants.user:UserID,FullName');

            return response()->json(['data' => $conversation], 201);
        });
    }
}
