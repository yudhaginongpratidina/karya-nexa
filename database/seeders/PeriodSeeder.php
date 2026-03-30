<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Period;

class PeriodSeeder extends Seeder
{
    public function run(): void
    {
        Period::insert([
            [
                'period_name' => '2024 Q1',
                'is_finalized' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'period_name' => '2024 Q2',
                'is_finalized' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'period_name' => '2024 Q3',
                'is_finalized' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}