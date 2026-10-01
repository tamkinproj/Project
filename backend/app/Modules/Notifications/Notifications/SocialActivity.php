<?php

namespace App\Modules\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

// Only identifiers are stored. Actor names are resolved when read, so renames,
// blocks and account deletion are always respected.
class SocialActivity extends Notification implements ShouldQueue
{
    use Queueable;

    public const FOLLOWED = 'followed';

    public const COMMENTED = 'commented';

    public const REACTED = 'reacted';

    public function __construct(
        public readonly string $event,
        public readonly string $actorId,
        public readonly ?string $postId = null,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'social',
            'event' => $this->event,
            'actor_id' => $this->actorId,
            'post_id' => $this->postId,
        ];
    }

    public function databaseType(object $notifiable): string
    {
        return 'social.'.$this->event;
    }
}
