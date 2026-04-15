<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
/**
 * Notification Controller - Manages user notifications.
 */

use OpenApi\Attributes as OA;

/**
 * Notification Controller - Manages user notifications.
 */
class NotificationController extends Controller
{
    /**
     * Get user notifications.
     */
    #[OA\Get(
        path: '/notifications',
        operationId: 'getNotifications',
        tags: ['Notifications'],
        summary: 'Get notifications',
        description: 'Get notifications. Note: New notifications are also broadcasted via Laravel Reverb websockets on the channel `private-App.Models.User.{id}`.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20))]
    #[OA\Response(response: 200, description: 'List of notifications')]
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
    #[OA\Patch(
        path: '/notifications/{id}/read',
        operationId: 'markNotificationRead',
        tags: ['Notifications'],
        summary: 'Mark notification as read',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Notification marked as read')]
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $updated = DB::table('notification')
            ->where('NotificationID', $id)
            ->where('UserID', $request->user()->UserID)
            ->update(['IsRead' => true]);

        if (! $updated) {
            return response()->json(['message' => 'Notification not found or unauthorized'], 404);
        }

        return response()->json(['message' => 'Notification marked as read']);
    }

    /**
     * Mark all notifications as read.
     */
    #[OA\Patch(
        path: '/notifications/read-all',
        operationId: 'markAllNotificationsRead',
        tags: ['Notifications'],
        summary: 'Mark all notifications as read',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Response(response: 200, description: 'All notifications marked as read')]
    public function markAllAsRead(Request $request): JsonResponse
    {
        DB::table('notification')
            ->where('UserID', $request->user()->UserID)
            ->where('IsRead', false)
            ->orWhereNull('IsRead')
            ->update(['IsRead' => true]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    /**
     * Get user's notification settings.
     */
    #[OA\Get(
        path: '/notifications/settings',
        operationId: 'getNotificationSettings',
        tags: ['Notifications'],
        summary: 'Get notification settings',
        description: 'Returns the current notification preferences for the user.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Response(response: 200, description: 'Notification settings')]
    public function getSettings(Request $request): JsonResponse
    {
        $settings = \App\Domain\Communication\Models\NotificationSetting::firstOrCreate(
            ['UserID' => $request->user()->UserID],
            [
                'EmailNotifications' => true,
                'PushNotifications' => true,
                'JobAlerts' => true,
                'ApplicationUpdates' => true,
                'MarketingEmails' => false,
            ]
        );

        return response()->json(['data' => $settings]);
    }

    /**
     * Update user's notification settings.
     */
    #[OA\Put(
        path: '/notifications/settings',
        operationId: 'updateNotificationSettings',
        tags: ['Notifications'],
        summary: 'Update notification settings',
        description: 'Updates the notification preferences for the user.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'email_notifications', type: 'boolean'),
                new OA\Property(property: 'push_notifications', type: 'boolean'),
                new OA\Property(property: 'job_alerts', type: 'boolean'),
                new OA\Property(property: 'application_updates', type: 'boolean'),
                new OA\Property(property: 'marketing_emails', type: 'boolean'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Settings updated successfully')]
    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'email_notifications' => 'sometimes|boolean',
            'push_notifications' => 'sometimes|boolean',
            'job_alerts' => 'sometimes|boolean',
            'application_updates' => 'sometimes|boolean',
            'marketing_emails' => 'sometimes|boolean',
        ]);

        $settings = \App\Domain\Communication\Models\NotificationSetting::firstOrCreate(
            ['UserID' => $request->user()->UserID]
        );

        if ($request->has('email_notifications')) $settings->EmailNotifications = $request->input('email_notifications');
        if ($request->has('push_notifications')) $settings->PushNotifications = $request->input('push_notifications');
        if ($request->has('job_alerts')) $settings->JobAlerts = $request->input('job_alerts');
        if ($request->has('application_updates')) $settings->ApplicationUpdates = $request->input('application_updates');
        if ($request->has('marketing_emails')) $settings->MarketingEmails = $request->input('marketing_emails');

        $settings->save();

        return response()->json([
            'message' => 'Settings updated successfully',
            'data' => $settings
        ]);
    }
}
