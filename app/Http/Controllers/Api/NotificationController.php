<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Communication\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notification Controller - Manages user notifications.
 */
class NotificationController extends Controller
{
    /**
     * Get user's notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::where('UserID', $request->user()->UserID)
            ->orderByDesc('CreatedAt')
            ->paginate(20);

        return response()->json($notifications);
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $notification = Notification::where('NotificationID', $id)
            ->where('UserID', $request->user()->UserID)
            ->firstOrFail();

        $notification->update(['IsRead' => true]);

        return response()->json(['message' => 'Notification marked as read']);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        Notification::where('UserID', $request->user()->UserID)
            ->where('IsRead', false)
            ->update(['IsRead' => true]);

        return response()->json(['message' => 'All notifications marked as read']);
    }
}
