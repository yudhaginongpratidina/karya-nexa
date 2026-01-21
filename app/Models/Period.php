<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Period extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_name',
        'is_finalized',
    ];

    /**
     * Casting atribut ke tipe data tertentu.
     */
    protected $casts = [
        'is_finalized' => 'boolean',
    ];

    // relasi ke tabel performance
    public function performances(): HasMany
    {
        return $this->hasMany(Performance::class);
    }
}
