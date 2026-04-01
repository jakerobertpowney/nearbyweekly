<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class WeeklyNewsletterMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param  array<string, list<array>>  $matches  Bucketed candidates keyed by bucket name.
     * @param  array{
     *     day_type: string,
     *     intro_line: string,
     *     bucket_labels: array<string, string>,
     *     max_per_bucket: array<string, int>
     * }  $newsletterContext  Timing-aware display context from NewsletterCurator.
     */
    public function __construct(
        public User $user,
        public array $matches,
        public string $unsubscribeUrl,
        public array $newsletterContext = [],
        public array $seasonalPicks = [],
    ) {}

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-PM-Message-Stream' => 'broadcast',
            ],
        );
    }

    /**
     * Get the message envelope.
     *
     * The subject line adapts to the day of send so it always feels relevant.
     */
    public function envelope(): Envelope
    {
        $dayType = $this->newsletterContext['day_type'] ?? 'normal';

        $subject = match ($dayType) {
            'saturday' => "Still happening near {$this->user->postcode} this weekend",
            'sunday' => "Plan your week — events near {$this->user->postcode}",
            'friday' => "This weekend near {$this->user->postcode} — what's on",
            default => "Your weekly events near {$this->user->postcode}",
        };

        return new Envelope(
            subject: $subject
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletters.weekly',
        );
    }
}
