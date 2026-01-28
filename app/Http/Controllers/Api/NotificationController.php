<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Notification Controller - Manages user notifications.
 */
class NotificationController extends Controller
{
    /**
     * Get user notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 20);

        $notifications = DB::table('notification')
            ->where('UserID', $request->user()->UserID)
            ->orderByDesc('CreatedAt')
            ->paginate($limit);

        return response()->json($notifications);
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $updated = DB::table('notification')
            ->where('NotificationID', $id)
            ->where('UserID', $request->user()->UserID)
            ->update(['ReadAt' => now()]);

        if (!$updated) {
            return response()->json(['message' => 'Notification not found or unauthorized'], 404);
        }

        return response()->json(['message' => 'Notification marked as read']);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        DB::table('notification')
            ->where('UserID', $request->user()->UserID)
            ->whereNull('ReadAt')
            ->update(['ReadAt' => now()]);

        return response()->json(['message' => 'All notifications marked as read']);
    }
}
