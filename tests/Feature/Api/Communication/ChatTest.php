<?php

namespace Tests\Feature\Api\Communication;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\DB;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_conversations()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $convId = DB::table('conversation')->insertGetId([
            'LatestMessageAt' => now(),
        ]);

        DB::table('conversationparticipant')->insert([
            ['ConversationID' => $convId, 'UserID' => $user1->UserID],
            ['ConversationID' => $convId, 'UserID' => $user2->UserID],
        ]);

        $response = $this->actingAs($user1)->getJson('/api/conversations');

        $response->assertStatus(200)
            ->assertJsonFragment(['ConversationID' => $convId]);
    }

    public function test_user_can_send_message()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $convId = DB::table('conversation')->insertGetId([]);
        DB::table('conversationparticipant')->insert([
            ['ConversationID' => $convId, 'UserID' => $user1->UserID],
            ['ConversationID' => $convId, 'UserID' => $user2->UserID],
        ]);

        $response = $this->actingAs($user1)->postJson("/api/conversations/{$convId}/messages", [
            'content' => 'Hello World',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['Content' => 'Hello World']);

        $this->assertDatabaseHas('message', [
            'ConversationID' => $convId,
            'SenderID' => $user1->UserID,
            'Content' => 'Hello World',
        ]);
    }

    public function test_user_can_view_messages()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $convId = DB::table('conversation')->insertGetId([]);
        DB::table('conversationparticipant')->insert([
            ['ConversationID' => $convId, 'UserID' => $user1->UserID],
            ['ConversationID' => $convId, 'UserID' => $user2->UserID],
        ]);

        DB::table('message')->insert([
            'ConversationID' => $convId,
            'SenderID' => $user2->UserID,
            'Content' => 'Hi there',
            'SentAt' => now(),
        ]);

        $response = $this->actingAs($user1)->getJson("/api/conversations/{$convId}/messages");

        $response->assertStatus(200)
            ->assertJsonFragment(['Content' => 'Hi there']);
    }
}
