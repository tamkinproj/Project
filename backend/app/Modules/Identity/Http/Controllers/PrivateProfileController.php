<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Http\Requests\UpdatePrivateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrivateProfileController
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->present($request->user()->privateProfile)]);
    }

    public function update(UpdatePrivateProfileRequest $request, AuditLogger $audit): JsonResponse
    {
        $private = $request->user()->privateProfile()->firstOrNew();
        $private->fill($request->validated())->save();

        // Field names only; the values are personal data and stay out of the audit trail.
        $audit->record('profile.private_updated', subject: $request->user(), metadata: ['fields' => array_keys($request->validated())]);

        return response()->json(['data' => $this->present($private)]);
    }

    private function present($private): array
    {
        return [
            'legal_name' => $private?->legal_name,
            'phone' => $private?->phone,
            'date_of_birth' => $private?->date_of_birth,
        ];
    }
}
