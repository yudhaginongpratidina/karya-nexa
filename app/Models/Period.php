<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
}
