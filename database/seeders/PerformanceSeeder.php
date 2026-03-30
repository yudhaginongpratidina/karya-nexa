<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Performance;

class PerformanceSeeder extends Seeder
{
    public function run(): void
    {
        Performance::insert([
            [
                'user_id' => 1,
                'criteria_id' => 1,
                'period_id' => 1,
                'score' => 85.50,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 1,
                'criteria_id' => 2,
                'period_id' => 1,
                'score' => 90.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 2,
                'criteria_id' => 1,
                'period_id' => 1,
                'score' => 78.25,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}