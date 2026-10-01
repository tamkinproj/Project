<?php

namespace App\Modules\Identity\Models;

use App\Support\Media\MediaUrl;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    use HasUlids;

    protected $fillable = ['username', 'display_name', 'bio', 'location', 'avatar_path'];

    protected function username(): Attribute
    {
        return Attribute::make(set: fn (string $value) => mb_strtolower(trim($value)));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? MediaUrl::for($this->avatar_path) : null;
    }
}
