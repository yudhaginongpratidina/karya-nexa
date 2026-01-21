<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Criteria extends Model
{
    protected $fillable = ['category_id', 'name', 'weight', 'type'];

    // relasi ke tabel category
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
