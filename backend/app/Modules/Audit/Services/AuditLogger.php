<?php

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

// Records security-relevant events. Never pass secrets (passwords, tokens, codes, chip UIDs) in $metadata.
class AuditLogger
{
    public function __construct(private readonly Request $request) {}

    public function record(
        string $action,
        ?User $actor = null,
        ?Model $subject = null,
        array $metadata = [],
        string $actorType = 'user',
    ): AuditLog {
        $actor ??= $this->request->user();

        return AuditLog::create([
            'actor_type' => $actor ? $actorType : 'system',
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip_address' => $this->request->ip(),
            'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 512) ?: null,
            'request_id' => $this->request->attributes->get('request_id'),
            'metadata' => $metadata ?: null,
        ]);
    }
}
