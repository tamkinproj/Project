<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Modules\Audit\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action' => ['nullable', 'string', 'max:64'],
            'actor_id' => ['nullable', 'string', 'size:26'],
            'subject_id' => ['nullable', 'string', 'size:26'],
        ]);

        $logs = AuditLog::query()
            ->when($data['action'] ?? null, fn ($q, $a) => $q->where('action', 'like', str_replace(['%', '_'], ['\\%', '\\_'], $a).'%'))
            ->when($data['actor_id'] ?? null, fn ($q, $id) => $q->where('actor_id', strtolower($id)))
            ->when($data['subject_id'] ?? null, fn ($q, $id) => $q->where('subject_id', strtolower($id)))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(50);

        return response()->json([
            'data' => $logs->getCollection()->map(fn (AuditLog $l) => [
                'id' => $l->id,
                'action' => $l->action,
                'actor_type' => $l->actor_type,
                'actor_id' => $l->actor_id,
                'subject_type' => $l->subject_type,
                'subject_id' => $l->subject_id,
                'ip_address' => $l->ip_address,
                'user_agent' => $l->user_agent,
                'request_id' => $l->request_id,
                'metadata' => $l->metadata,
                'created_at' => $l->created_at->toIso8601String(),
            ]),
            'meta' => ['next_cursor' => $logs->nextCursor()?->encode()],
        ]);
    }
}
