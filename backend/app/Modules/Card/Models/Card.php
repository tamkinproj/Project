<?php

namespace App\Modules\Card\Models;

use App\Modules\Card\Enums\CardStatus;
use App\Modules\Card\Enums\CardType;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Card extends Model
{
    use HasUlids;

    protected $fillable = [
        'type', 'status', 'number_last4', 'number_hash', 'chip_uid_hash',
        'activation_code_hash', 'activation_expires_at', 'credential_version',
        'activated_at', 'frozen_at', 'lost_reported_at', 'replacement_requested_at', 'revoked_at',
    ];

    protected $hidden = ['number_hash', 'chip_uid_hash', 'activation_code_hash'];

    protected function casts(): array
    {
        return [
            'type' => CardType::class,
            'status' => CardStatus::class,
            'activation_expires_at' => 'datetime',
            'activated_at' => 'datetime',
            'frozen_at' => 'datetime',
            'lost_reported_at' => 'datetime',
            'replacement_requested_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function holder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_card_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CardEvent::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function isPhysical(): bool
    {
        return $this->type === CardType::Physical;
    }
}
