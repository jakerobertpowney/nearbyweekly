<?php

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendLoginLinkNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $url,
        public CarbonInterface $expiresAt,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your secure sign-in link')
            ->greeting('Hello!')
            ->line('Use the secure link below to sign in to your account.')
            ->action('Sign in securely', $this->url)
            ->line('This link expires in 15 minutes and can only be used once.')
            ->line('If you did not request this email, you can safely ignore it.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'expires_at' => $this->expiresAt->toIso8601String(),
            'url' => $this->url,
        ];
    }
}
