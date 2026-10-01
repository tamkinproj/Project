<?php

namespace App\Modules\Social\Models;

use App\Support\Media\MediaUrl;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostMedia extends Model
{
    use HasUlids;

    protected $table = 'post_media';

    protected $fillable = ['disk', 'path', 'mime_type', 'width', 'height', 'size_bytes', 'position'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function url(): string
    {
        return MediaUrl::for($this->path);
    }
}
