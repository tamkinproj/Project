<?php

namespace App\Modules\Identity\Enums;

enum MessagePolicy: string
{
    case Everyone = 'everyone';
    case Followers = 'followers';
    case Nobody = 'nobody';
}
