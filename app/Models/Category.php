<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'weight'
    ];

    // relasi ke tabel criteria (1 kategori memiliki banyak kriteria)
    public function criterias(): HasMany
    {
        return $this->hasMany(Criteria::class);
    }
}
