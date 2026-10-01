<?php

namespace App\Modules\Moderation\Http\Controllers;

use App\Modules\Moderation\Enums\ReportReason;
use App\Modules\Moderation\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController
{
    public function store(Request $request, ReportService $reports): JsonResponse
    {
        $data = $request->validate([
            'target_type' => ['required', Rule::in(['user', 'post', 'comment'])],
            'target_id' => ['required', 'string', 'max:64'],
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        $report = $reports->create(
            $request->user(),
            $data['target_type'],
            $data['target_id'],
            ReportReason::from($data['reason']),
            $data['details'] ?? null,
        );

        // Reporters only learn that the report was received, never how it was handled.
        return response()->json(['id' => $report->id, 'status' => 'received'], 201);
    }
}
