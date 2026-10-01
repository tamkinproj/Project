<?php

namespace App\Modules\Moderation\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Moderation\Enums\ModerationActionType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModerationAction extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = ['moderator_id', 'action', 'target_type', 'target_id', 'report_id', 'reason'];

    protected function casts(): array
    {
        return ['action' => ModerationActionType::class, 'created_at' => 'datetime'];
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }
}
