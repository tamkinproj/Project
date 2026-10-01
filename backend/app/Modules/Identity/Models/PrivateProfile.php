<?php

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Personal data that is never exposed to other users. Encrypted at rest.
class PrivateProfile extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['legal_name', 'phone', 'date_of_birth'];

    protected function casts(): array
    {
        return [
            'legal_name' => 'encrypted',
            'phone' => 'encrypted',
            'date_of_birth' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
