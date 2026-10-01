<?php

namespace App\Support\Media;

use Illuminate\Support\Facades\Storage;

// Media is private; clients only ever receive short-lived signed URLs.
class MediaUrl
{
    public static function for(string $path): string
    {
        $ttl = max(5, config('ecosystem.media.url_ttl_minutes'));

        // Expiry is aligned to a time bucket so the same URL is reused within a window,
        // letting browsers and CDNs cache images instead of re-downloading on every request.
        $bucketSeconds = $ttl * 60;
        $expiresAt = (intdiv(time(), $bucketSeconds) + 2) * $bucketSeconds;

        return Storage::disk(config('ecosystem.media.disk'))
            ->temporaryUrl($path, now()->setTimestamp($expiresAt));
    }
}
