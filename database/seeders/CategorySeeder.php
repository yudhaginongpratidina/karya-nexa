<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'pengelolahan produk dan konten', 'weight' => 0.18],
            ['name' => 'pengelolahan order', 'weight' => 0.25],
            ['name' => 'customer service', 'weight' => 0.22],
            ['name' => 'operasional marketplace', 'weight' => 0.25],
            ['name' => 'target penjualan dan pertumbuhan', 'weight' => 0.20],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name']], 
                ['weight' => $category['weight']]
            );
        }
    }
}