<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Criteria;

class CriteriaSeeder extends Seeder
{
    public function run(): void
    {
        Criteria::insert([
            [
                'category_id' => 1,
                'name' => 'Harga',
                'weight' => 0.3,
                'type' => 'cost'
            ],
            [
                'category_id' => 1,
                'name' => 'Kualitas',
                'weight' => 0.4,
                'type' => 'benefit'
            ],
            [
                'category_id' => 1,
                'name' => 'Fitur',
                'weight' => 0.3,
                'type' => 'benefit'
            ]
        ]);
    }
}
