<?php

namespace App\Modules\Social\Enums;

enum PostVisibility: string
{
    case Public = 'public';
    case Followers = 'followers';
    case OnlyMe = 'only_me';
}
