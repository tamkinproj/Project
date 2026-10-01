<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Validation\Rule;

final class UsernameRules
{
    // Handles that could be used to impersonate the platform or collide with routes.
    private const RESERVED = [
        'admin', 'administrator', 'root', 'system', 'support', 'help', 'security', 'staff',
        'moderator', 'mod', 'official', 'team', 'api', 'www', 'mail', 'settings', 'login',
        'logout', 'register', 'signup', 'signin', 'me', 'profile', 'notifications', 'card',
        'cards', 'wallet', 'shop', 'charity', 'masjid', 'discover', 'about', 'terms',
        'privacy', 'search', 'feed', 'posts', 'null', 'undefined', 'anonymous', 'ummah',
    ];

    /** @return array<int, mixed> */
    public static function rules(?string $ignoreUserId = null): array
    {
        $unique = Rule::unique('profiles', 'username');

        if ($ignoreUserId) {
            $unique->ignore($ignoreUserId, 'user_id');
        }

        return [
            'required', 'string', 'min:3', 'max:30',
            'regex:/^[a-z0-9](?:[a-z0-9._]*[a-z0-9])?$/',
            'not_regex:/[._]{2}/',
            Rule::notIn(self::RESERVED),
            $unique,
        ];
    }

    public static function messages(): array
    {
        return [
            'username.regex' => 'Usernames may contain lowercase letters, numbers, dots and underscores, and must start and end with a letter or number.',
            'username.not_regex' => 'Usernames cannot contain consecutive dots or underscores.',
            'username.not_in' => 'This username is reserved.',
        ];
    }

    public static function normalize(mixed $value): mixed
    {
        return is_string($value) ? mb_strtolower(trim(ltrim(trim($value), '@'))) : $value;
    }
}
