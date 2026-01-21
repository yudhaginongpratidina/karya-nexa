<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Criteria extends Model
{
    protected $fillable = ['category_id', 'name', 'weight', 'type'];

    // relasi ke tabel category
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // relasi ke tabel performance
    public function performances(): HasMany
    {
        return $this->hasMany(Performance::class);
    }
}
