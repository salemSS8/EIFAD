<?php

namespace App\Mail;

use App\Domain\Job\Models\JobAd;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewApplicationReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $jobTitle;

    public string $applicantName;

    /**
     * Create a new message instance.
     */
    public function __construct(JobAd $jobAd, string $applicantName)
    {
        $this->jobTitle = $jobAd->Title;
        $this->applicantName = $applicantName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "تقديم جديد على وظيفتك: {$this->jobTitle}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.notifications.new-application',
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
