<?php

namespace App\Events;

use App\Domain\Application\Models\JobApplication;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class ApplicationStatusUpdated implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public JobApplication $application
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        // Broadcast to the Job Seeker's private channel
        return [
            new PrivateChannel('user.' . $this->application->JobSeekerID),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'application.status.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'application_id' => $this->application->ApplicationID,
            'job_title' => $this->application->jobAd->Title ?? 'Unknown Job',
            'new_status' => $this->application->Status,
            'updated_at' => now(),
        ];
    }
}
