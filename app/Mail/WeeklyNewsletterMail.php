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
        public array $newsletterContext = [],
        public array $seasonalPicks = [],
        public ?string $generatedSubject = null,
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
     */
    public function envelope(): Envelope
    {
        $postcode = strtoupper(explode(' ', trim($this->user->postcode))[0]);

        return new Envelope(subject: "📍 What's on near {$postcode} this week");
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $outwardCode = explode(' ', trim($this->user->postcode))[0];

        $totalEvents = array_sum(array_map('count', $this->matches)) + count($this->seasonalPicks);

        return new Content(
            view: 'emails.newsletters.weekly',
            with: [
                'outwardCode' => $outwardCode,
                'totalEvents' => $totalEvents,
            ],
        );
    }
}
