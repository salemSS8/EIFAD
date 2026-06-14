<?php

namespace App\Mail;

use App\Domain\Job\Models\JobAd;
use App\Domain\User\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewJobMatchMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $jobTitle;

    public string $companyName;

    public string $userName;

    public ?string $location;

    public ?string $workType;

    public ?string $workplaceType;

    /**
     * Create a new message instance.
     */
    public function __construct(JobAd $jobAd, User $user, string $companyName)
    {
        $this->jobTitle = $jobAd->Title;
        $this->companyName = $companyName;
        $this->userName = $user->FullName ?? 'باحث عن عمل';
        $this->location = $jobAd->Location;
        $this->workType = $jobAd->WorkType;
        $this->workplaceType = $jobAd->WorkplaceType;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "وظيفة جديدة تناسبك: {$this->jobTitle}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.notifications.new-job-match',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
