<?php

namespace App\Modules\Moderation\Enums;

enum ReportReason: string
{
    case Spam = 'spam';
    case Harassment = 'harassment';
    case Hate = 'hate';
    case Violence = 'violence';
    case SexualContent = 'sexual_content';
    case Misinformation = 'misinformation';
    case Impersonation = 'impersonation';
    case Other = 'other';
}
