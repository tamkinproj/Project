<?php

namespace App\Modules\Moderation\Http\Controllers\Admin;

use App\Modules\Identity\Models\User;
use App\Modules\Moderation\Enums\ReportStatus;
use App\Modules\Moderation\Http\Resources\AdminReportResource;
use App\Modules\Moderation\Models\Report;
use App\Modules\Moderation\Services\ModerationService;
use App\Modules\Social\Models\Comment;
use App\Modules\Social\Models\Post;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ReportAdminController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $status = ReportStatus::tryFrom((string) $request->query('status', 'open')) ?? ReportStatus::Open;

        $reports = $this->query()
            ->where('status', $status)
            ->orderBy($status === ReportStatus::Open ? 'id' : 'reviewed_at', $status === ReportStatus::Open ? 'asc' : 'desc')
            ->paginate(25);

        return AdminReportResource::collection($reports);
    }

    public function show(string $report): AdminReportResource
    {
        return new AdminReportResource($this->query()->findOrFail($report));
    }

    public function resolve(Request $request, string $report, ModerationService $moderation): AdminReportResource
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['dismiss', 'hide_content', 'suspend_user'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $model = $this->query()->findOrFail($report);
        $moderation->resolve($request->user(), $model, $data['decision'], $data['note'] ?? null);

        return new AdminReportResource($this->query()->findOrFail($report));
    }

    private function query()
    {
        return Report::query()->with([
            'reporter.profile',
            'reviewer.profile',
            'reportable' => fn (MorphTo $morph) => $morph->morphWith([
                Post::class => ['author.profile', 'media'],
                Comment::class => ['author.profile'],
                User::class => ['profile'],
            ]),
        ]);
    }
}
