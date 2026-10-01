<?php

namespace App\Modules\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Security-sensitive events. Rendered distinctly from social notifications in every client.
class SecurityAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $event,
        public readonly string $message,
        public readonly bool $sendEmail = false,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return $this->sendEmail ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Security alert: '.config('app.name'))
            ->line($this->message)
            ->line('If this was not you, reset your password immediately and sign out of all other devices.')
            ->action('Review account security', config('ecosystem.frontend_url').'/settings/security');
    }

    public function toArray(object $notifiable): array
    {
        return ['category' => 'security', 'event' => $this->event, 'message' => $this->message];
    }

    public function databaseType(object $notifiable): string
    {
        return 'security.'.$this->event;
    }
}
