<?php

namespace App\Modules\Admin\Enums;

// The complete list of administrative permissions. Roles grant subsets of these;
// a permission string not listed here is never honoured.
enum Permission: string
{
    case UsersView = 'users.view';
    case UsersSuspend = 'users.suspend';
    case ReportsReview = 'reports.review';
    case ContentModerate = 'content.moderate';
    case CardsIssue = 'cards.issue';
    case CardsManage = 'cards.manage';
    case AuditView = 'audit.view';
    case RolesManage = 'roles.manage';
    case SystemHealth = 'system.health';

    public function label(): string
    {
        return match ($this) {
            self::UsersView => 'View user accounts',
            self::UsersSuspend => 'Suspend and restore accounts',
            self::ReportsReview => 'Review reports',
            self::ContentModerate => 'Hide and restore content',
            self::CardsIssue => 'Issue physical cards',
            self::CardsManage => 'Manage and revoke cards',
            self::AuditView => 'View audit logs',
            self::RolesManage => 'Assign administrative roles',
            self::SystemHealth => 'View system health',
        };
    }
}
