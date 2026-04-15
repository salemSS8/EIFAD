<?php

namespace Tests\Feature\Api\Communication;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\DB;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_notifications()
    {
        $user = User::factory()->create();

        DB::table('notification')->insert([
            'UserID' => $user->UserID,
            'Type' => 'TestType',
            'Content' => 'Hello World',
            'CreatedAt' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['NotificationID', 'Type', 'Content']]]);
    }

    public function test_user_can_mark_notification_as_read()
    {
        $user = User::factory()->create();

        $notifId = DB::table('notification')->insertGetId([
            'UserID' => $user->UserID,
            'Type' => 'TestType',
            'Content' => 'test',
            'IsRead' => false,
            'CreatedAt' => now(),
        ]);

        $response = $this->actingAs($user)->putJson("/api/notifications/{$notifId}/read");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Notification marked as read']);

        $this->assertDatabaseHas('notification', [
            'NotificationID' => $notifId,
            'IsRead' => true,
        ]);

        $notif = DB::table('notification')->where('NotificationID', $notifId)->first();
        $this->assertTrue((bool) $notif->IsRead);
    }

    public function test_user_cannot_mark_others_notification_as_read()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $notifId = DB::table('notification')->insertGetId([
            'UserID' => $user2->UserID, // Belongs to user 2
            'Type' => 'TestType',
            'Content' => 'test',
            'CreatedAt' => now(),
        ]);

        $response = $this->actingAs($user1)->putJson("/api/notifications/{$notifId}/read");

        $response->assertStatus(404);
    }

    public function test_user_can_mark_all_notifications_as_read()
    {
        $user = User::factory()->create();

        DB::table('notification')->insert([
            ['UserID' => $user->UserID, 'Type' => 'T1', 'Content' => 'D1', 'CreatedAt' => now(), 'IsRead' => false],
            ['UserID' => $user->UserID, 'Type' => 'T2', 'Content' => 'D2', 'CreatedAt' => now(), 'IsRead' => false],
        ]);

        $response = $this->actingAs($user)->putJson("/api/notifications/read-all");

        $response->assertStatus(200)
            ->assertJson(['message' => 'All notifications marked as read']);

        $unreadCount = DB::table('notification')
            ->where('UserID', $user->UserID)
            ->where(function ($q) {
                $q->where('IsRead', false)->orWhereNull('IsRead');
            })
            ->count();

        $this->assertEquals(0, $unreadCount);
    }

    public function test_user_can_get_notification_settings()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/notifications/settings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'SettingID',
                    'UserID',
                    'EmailNotifications',
                    'PushNotifications',
                    'JobAlerts',
                    'ApplicationUpdates',
                    'MarketingEmails'
                ]
            ]);
    }

    public function test_user_can_update_notification_settings()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/notifications/settings', [
            'marketing_emails' => true,
            'push_notifications' => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.MarketingEmails', true)
            ->assertJsonPath('data.PushNotifications', false);

        $this->assertDatabaseHas('notification_settings', [
            'UserID' => $user->UserID,
            'MarketingEmails' => true,
            'PushNotifications' => false,
        ]);
    }
}
