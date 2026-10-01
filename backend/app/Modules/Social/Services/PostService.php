<?php

namespace App\Modules\Social\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Social\Enums\PostVisibility;
use App\Modules\Social\Models\Post;
use App\Support\Media\ImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PostService
{
    public function __construct(private readonly ImageProcessor $images) {}

    /** @param array<int, UploadedFile> $images */
    public function create(User $author, ?string $body, PostVisibility $visibility, array $images = []): Post
    {
        $stored = [];

        try {
            foreach ($images as $image) {
                $stored[] = $this->images->store($image, 'posts/'.now()->format('Y/m'));
            }

            return DB::transaction(function () use ($author, $body, $visibility, $stored) {
                $post = $author->posts()->create([
                    'body' => self::cleanBody($body),
                    'visibility' => $visibility,
                ]);

                foreach ($stored as $position => $media) {
                    $post->media()->create([...$media, 'position' => $position]);
                }

                return $post;
            });
        } catch (Throwable $e) {
            $this->deleteFiles(array_column($stored, 'path'));
            throw $e;
        }
    }

    public function update(Post $post, array $changes): Post
    {
        if (array_key_exists('body', $changes)) {
            $changes['body'] = self::cleanBody($changes['body']);
            $changes['edited_at'] = now();
        }

        $post->update($changes);

        return $post;
    }

    public function delete(Post $post): void
    {
        $paths = $post->media()->pluck('path')->all();

        DB::transaction(fn () => $post->delete());

        $this->deleteFiles($paths);
    }

    public static function cleanBody(?string $body): ?string
    {
        if ($body === null) {
            return null;
        }

        // Strip raw control characters (not format characters: RTL marks and emoji joiners
        // are legitimate in Arabic text and emoji). Rendering clients escape all output.
        $body = str_replace("\r\n", "\n", $body);
        $body = preg_replace('/[^\P{Cc}\n\t]/u', '', $body) ?? '';
        $body = preg_replace("/\n{3,}/", "\n\n", trim($body)) ?? '';

        return $body === '' ? null : $body;
    }

    /** @param array<int, string> $paths */
    private function deleteFiles(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk(config('ecosystem.media.disk'))->delete($paths);
        }
    }
}
