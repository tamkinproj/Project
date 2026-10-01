<?php

namespace App\Modules\Moderation\Enums;

enum ReportStatus: string
{
    case Open = 'open';
    case Actioned = 'actioned';
    case Dismissed = 'dismissed';
}
