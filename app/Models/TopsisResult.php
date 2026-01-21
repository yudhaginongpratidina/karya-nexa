<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopsisResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'period_id',
        'preference_value',
        'rank'
    ];

    /**
     * Relasi ke User (Karyawan yang dinilai)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi ke Periode penilaian
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }
}
