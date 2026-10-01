<?php

namespace App\Modules\Moderation\Enums;

enum ModerationActionType: string
{
    case HidePost = 'hide_post';
    case RestorePost = 'restore_post';
    case HideComment = 'hide_comment';
    case RestoreComment = 'restore_comment';
    case SuspendUser = 'suspend_user';
    case UnsuspendUser = 'unsuspend_user';
    case DismissReport = 'dismiss_report';
}
