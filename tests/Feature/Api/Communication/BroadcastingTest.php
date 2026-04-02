<?php

namespace Tests\Feature\Api\Communication;

use App\Domain\Company\Models\CompanyProfile;
use App\Domain\User\Models\User;
use App\Events\MessageSent;
use App\Events\NotificationReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BroadcastingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sending_message_dispatches_event()
    {
        Event::fake([MessageSent::class]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $convId = DB::table('conversation')->insertGetId([]);
        DB::table('conversationparticipant')->insert([
            ['ConversationID' => $convId, 'UserID' => $user1->UserID],
            ['ConversationID' => $convId, 'UserID' => $user2->UserID],
        ]);

        $response = $this->actingAs($user1)->postJson("/api/conversations/{$convId}/messages", [
            'content' => 'Hello Realtime',
        ]);

        $response->assertStatus(201);

        Event::assertDispatched(MessageSent::class, function ($event) use ($convId) {
            return $event->message->ConversationID === $convId && $event->message->Content === 'Hello Realtime';
        });
    }

    public function test_updating_application_status_dispatches_notification_event()
    {
        Event::fake([NotificationReceived::class]);

        $employer = User::factory()->create();
        CompanyProfile::create([
            'CompanyID' => $employer->UserID,
            'CompanyName' => 'Test Company',
        ]);

        $jobSeeker = User::factory()->create();
        DB::table('jobseekerprofile')->insert([
            'JobSeekerID' => $jobSeeker->UserID,
        ]);

        $jobId = DB::table('jobad')->insertGetId([
            'CompanyID' => $employer->UserID,
            'Title' => 'Software Engineer',
            'Description' => 'Test Job',
            'Status' => 'Active',
            'PostedAt' => now(),
        ]);

        $cvId = DB::table('cv')->insertGetId([
            'JobSeekerID' => $jobSeeker->UserID,
            'Title' => 'My CV',
            'CreatedAt' => now(),
        ]);

        $appId = DB::table('jobapplication')->insertGetId([
            'JobAdID' => $jobId,
            'JobSeekerID' => $jobSeeker->UserID,
            'AppliedAt' => now(),
            'Status' => 'Pending',
            'CVID' => $cvId,
        ]);

        $response = $this->actingAs($employer)->putJson("/api/employer/applications/{$appId}/status", [
            'status' => 'Shortlisted',
        ]);

        $response->assertStatus(200);

        Event::assertDispatched(NotificationReceived::class, function ($event) use ($jobSeeker) {
            return $event->notification->UserID === $jobSeeker->UserID && $event->notification->Type === 'Application Update';
        });
    }
}
