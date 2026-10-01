<?php

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Enums\FollowPolicy;
use App\Modules\Identity\Enums\MessagePolicy;
use App\Modules\Social\Enums\PostVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivacySetting extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'profile_searchable',
        'show_location',
        'who_can_follow',
        'who_can_message',
        'default_post_visibility',
    ];

    protected $attributes = [
        'profile_searchable' => true,
        'show_location' => true,
        'who_can_follow' => 'everyone',
        'who_can_message' => 'followers',
        'default_post_visibility' => 'public',
    ];

    protected function casts(): array
    {
        return [
            'profile_searchable' => 'boolean',
            'show_location' => 'boolean',
            'who_can_follow' => FollowPolicy::class,
            'who_can_message' => MessagePolicy::class,
            'default_post_visibility' => PostVisibility::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
