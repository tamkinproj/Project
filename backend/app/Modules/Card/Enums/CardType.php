<?php

namespace App\Modules\Card\Enums;

enum CardType: string
{
    // The member card shown in the app; every account has one.
    case Virtual = 'virtual';
    // A physical RFID/NFC card issued by staff and linked by the user.
    case Physical = 'physical';
}
