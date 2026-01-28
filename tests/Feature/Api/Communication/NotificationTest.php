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
            'Data' => json_encode(['message' => 'Hello']),
            'CreatedAt' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['NotificationID', 'Type', 'Data']]]);
    }

    public function test_user_can_mark_notification_as_read()
    {
        $user = User::factory()->create();

        $notifId = DB::table('notification')->insertGetId([
            'UserID' => $user->UserID,
            'Type' => 'TestType',
            'Data' => 'test',
            'CreatedAt' => now(),
        ]);

        $response = $this->actingAs($user)->putJson("/api/notifications/{$notifId}/read");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Notification marked as read']);

        $this->assertDatabaseHas('notification', [
            'NotificationID' => $notifId,
        ]);

        $notif = DB::table('notification')->where('NotificationID', $notifId)->first();
        $this->assertNotNull($notif->ReadAt);
    }

    public function test_user_cannot_mark_others_notification_as_read()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $notifId = DB::table('notification')->insertGetId([
            'UserID' => $user2->UserID, // Belongs to user 2
            'Type' => 'TestType',
            'Data' => 'test',
            'CreatedAt' => now(),
        ]);

        $response = $this->actingAs($user1)->putJson("/api/notifications/{$notifId}/read");

        $response->assertStatus(404);
    }

    public function test_user_can_mark_all_notifications_as_read()
    {
        $user = User::factory()->create();

        DB::table('notification')->insert([
            ['UserID' => $user->UserID, 'Type' => 'T1', 'Data' => 'D1', 'CreatedAt' => now()],
            ['UserID' => $user->UserID, 'Type' => 'T2', 'Data' => 'D2', 'CreatedAt' => now()],
        ]);

        $response = $this->actingAs($user)->putJson("/api/notifications/read-all");

        $response->assertStatus(200)
            ->assertJson(['message' => 'All notifications marked as read']);

        $unreadCount = DB::table('notification')
            ->where('UserID', $user->UserID)
            ->whereNull('ReadAt')
            ->count();

        $this->assertEquals(0, $unreadCount);
    }
}
