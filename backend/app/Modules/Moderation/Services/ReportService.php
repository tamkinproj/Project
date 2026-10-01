<?php

namespace App\Modules\Moderation\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Moderation\Enums\ReportReason;
use App\Modules\Moderation\Enums\ReportStatus;
use App\Modules\Moderation\Models\Report;
use App\Modules\Social\Enums\ModerationStatus;
use App\Modules\Social\Enums\PostVisibility;
use App\Modules\Social\Models\Comment;
use App\Modules\Social\Models\Post;
use App\Modules\Social\Services\ProfileLookup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReportService
{
    public function __construct(private readonly ProfileLookup $profiles) {}

    // Blocking someone does not stop you reporting them: block-then-report is the normal flow.
    public function create(User $reporter, string $type, string $target, ReportReason $reason, ?string $details): Report
    {
        $reportable = match ($type) {
            'user' => $this->profiles->find($target, $reporter, ignoreBlocks: true),
            'post' => Post::where('moderation_status', ModerationStatus::Visible)
                ->where(fn ($q) => $q->where('visibility', '!=', PostVisibility::OnlyMe)->orWhere('user_id', $reporter->id))
                ->find($target),
            'comment' => Comment::where('moderation_status', ModerationStatus::Visible)->find($target),
            default => null,
        };

        if (! $reportable) {
            throw new NotFoundHttpException;
        }

        if ($this->ownerOf($reportable) === $reporter->id) {
            throw ValidationException::withMessages(['target' => 'You cannot report yourself or your own content.']);
        }

        $existing = Report::where('reporter_id', $reporter->id)
            ->where('reportable_type', $reportable->getMorphClass())
            ->where('reportable_id', $reportable->getKey())
            ->where('status', ReportStatus::Open)
            ->first();

        if ($existing) {
            return $existing;
        }

        $report = new Report(['reason' => $reason, 'details' => $details]);
        $report->reporter()->associate($reporter);
        $report->reportable()->associate($reportable);
        $report->save();

        return $report;
    }

    private function ownerOf(Model $reportable): string
    {
        return $reportable instanceof User ? $reportable->id : $reportable->user_id;
    }
}
