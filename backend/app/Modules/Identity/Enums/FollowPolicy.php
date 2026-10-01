<?php

namespace App\Modules\Identity\Enums;

enum FollowPolicy: string
{
    case Everyone = 'everyone';
    case Nobody = 'nobody';
}
