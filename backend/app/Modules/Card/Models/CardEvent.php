<?php

namespace App\Modules\Card\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardEvent extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = ['actor_id', 'event', 'ip_address', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
