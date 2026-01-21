<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Performance extends Model
{
    // Relasi balik ke User
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Relasi balik ke Criteria
    public function criteria(): BelongsTo
    {
        return $this->belongsTo(Criteria::class);
    }

    // Relasi balik ke Period
    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }
}
