<?php

namespace App\Modules\Card\Enums;

enum CardStatus: string
{
    case PendingActivation = 'pending_activation';
    case Active = 'active';
    case Frozen = 'frozen';
    case Lost = 'lost';
    case Replaced = 'replaced';
    case Revoked = 'revoked';

    // Terminal states can never become usable again; a new card must be issued.
    public function isTerminal(): bool
    {
        return in_array($this, [self::Lost, self::Replaced, self::Revoked], true);
    }
}
