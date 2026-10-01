<?php

namespace App\Modules\Moderation\Services;

use App\Modules\Admin\Enums\Permission;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\User;
use App\Modules\Moderation\Enums\ModerationActionType;
use App\Modules\Moderation\Enums\ReportStatus;
use App\Modules\Moderation\Models\ModerationAction;
use App\Modules\Moderation\Models\Report;
use App\Modules\Notifications\Notifications\SecurityAlert;
use App\Modules\Social\Enums\ModerationStatus;
use App\Modules\Social\Models\Comment;
use App\Modules\Social\Models\Post;
use App\Modules\Social\Services\PostCounters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ModerationService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PostCounters $counters,
    ) {}

    public function hidePost(User $moderator, Post $post, ?string $reason, ?Report $report = null): void
    {
        $this->setContentStatus($moderator, $post, ModerationStatus::Hidden, ModerationActionType::HidePost, $reason, $report);
    }

    public function restorePost(User $moderator, Post $post, ?string $reason): void
    {
        $this->setContentStatus($moderator, $post, ModerationStatus::Visible, ModerationActionType::RestorePost, $reason);
    }

    public function hideComment(User $moderator, Comment $comment, ?string $reason, ?Report $report = null): void
    {
        $this->setContentStatus($moderator, $comment, ModerationStatus::Hidden, ModerationActionType::HideComment, $reason, $report);
        $this->counters->refresh([$comment->post_id]);
    }

    public function restoreComment(User $moderator, Comment $comment, ?string $reason): void
    {
        $this->setContentStatus($moderator, $comment, ModerationStatus::Visible, ModerationActionType::RestoreComment, $reason);
        $this->counters->refresh([$comment->post_id]);
    }

    public function suspendUser(User $moderator, User $target, ?string $reason, ?Report $report = null): void
    {
        if ($target->is($moderator)) {
            throw ValidationException::withMessages(['user' => 'You cannot suspend your own account.']);
        }

        // Moderators must not be able to lock out administrators (privilege escalation by suspension).
        if ($target->isAdmin() && ! $moderator->hasPermission(Permission::RolesManage)) {
            throw new HttpException(403, 'Only role managers can suspend administrative accounts.');
        }

        DB::transaction(function () use ($moderator, $target, $reason, $report) {
            $target->forceFill(['status' => AccountStatus::Suspended, 'status_changed_at' => now()])->save();
            $target->tokens()->delete();

            $this->recordAction($moderator, ModerationActionType::SuspendUser, $target, $reason, $report);
            $this->closeOpenReports($moderator, $target, ReportStatus::Actioned, $reason);
        });

        $target->notify(new SecurityAlert('account_suspended', 'Your account has been suspended for violating the community guidelines.', sendEmail: true));
    }

    public function unsuspendUser(User $moderator, User $target, ?string $reason): void
    {
        if ($target->status !== AccountStatus::Suspended) {
            throw ValidationException::withMessages(['user' => 'This account is not suspended.']);
        }

        DB::transaction(function () use ($moderator, $target, $reason) {
            $target->forceFill(['status' => AccountStatus::Active, 'status_changed_at' => now()])->save();
            $this->recordAction($moderator, ModerationActionType::UnsuspendUser, $target, $reason);
        });
    }

    /** @param 'dismiss'|'hide_content'|'suspend_user' $decision */
    public function resolve(User $moderator, Report $report, string $decision, ?string $note): Report
    {
        if ($report->status !== ReportStatus::Open) {
            throw ValidationException::withMessages(['report' => 'This report has already been resolved.']);
        }

        $target = $report->reportable;

        if (! $target && $decision !== 'dismiss') {
            throw ValidationException::withMessages(['decision' => 'The reported content no longer exists. Dismiss the report instead.']);
        }

        match ($decision) {
            'dismiss' => DB::transaction(function () use ($moderator, $report, $note) {
                $this->markReport($moderator, $report, ReportStatus::Dismissed, $note);
                $this->recordAction($moderator, ModerationActionType::DismissReport, $report, $note, $report);
            }),
            'hide_content' => match (true) {
                $target instanceof Post => $this->hidePost($moderator, $target, $note, $report),
                $target instanceof Comment => $this->hideComment($moderator, $target, $note, $report),
                default => throw ValidationException::withMessages(['decision' => 'Accounts cannot be hidden. Suspend the account instead.']),
            },
            'suspend_user' => $this->suspendUser($moderator, $target instanceof User ? $target : $target->author, $note, $report),
        };

        // Suspending the author of reported content also closes the content report itself.
        if ($report->refresh()->status === ReportStatus::Open) {
            $this->markReport($moderator, $report, ReportStatus::Actioned, $note);
        }

        return $report;
    }

    private function setContentStatus(User $moderator, Post|Comment $content, ModerationStatus $status, ModerationActionType $action, ?string $reason, ?Report $report = null): void
    {
        DB::transaction(function () use ($moderator, $content, $status, $action, $reason, $report) {
            $content->forceFill(['moderation_status' => $status])->save();
            $this->recordAction($moderator, $action, $content, $reason, $report);

            if ($status === ModerationStatus::Hidden) {
                $this->closeOpenReports($moderator, $content, ReportStatus::Actioned, $reason);
            }
        });
    }

    private function closeOpenReports(User $moderator, Model $target, ReportStatus $status, ?string $note): void
    {
        Report::where('reportable_type', $target->getMorphClass())
            ->where('reportable_id', $target->getKey())
            ->where('status', ReportStatus::Open)
            ->get()
            ->each(fn (Report $r) => $this->markReport($moderator, $r, $status, $note));
    }

    private function markReport(User $moderator, Report $report, ReportStatus $status, ?string $note): void
    {
        $report->reviewer()->associate($moderator);
        $report->fill(['status' => $status, 'reviewed_at' => now(), 'resolution_note' => $note])->save();
    }

    private function recordAction(User $moderator, ModerationActionType $action, Model $target, ?string $reason, ?Report $report = null): void
    {
        ModerationAction::create([
            'moderator_id' => $moderator->id,
            'action' => $action,
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->getKey(),
            'report_id' => $report?->id,
            'reason' => $reason,
        ]);

        $this->audit->record('moderation.'.$action->value, actor: $moderator, subject: $target, metadata: array_filter(['report_id' => $report?->id]));
    }
}
