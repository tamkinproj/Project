<?php

namespace App\Modules\Identity\Enums;

enum AccountStatus: string
{
    case Active = 'active';
    // Chosen by the user; signing in again reactivates the account.
    case Deactivated = 'deactivated';
    // Imposed by a moderator; the user cannot sign in until lifted.
    case Suspended = 'suspended';
}
