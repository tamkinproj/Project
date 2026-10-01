<?php

namespace App\Modules\Social\Enums;

enum ModerationStatus: string
{
    case Visible = 'visible';
    case Hidden = 'hidden';
}
