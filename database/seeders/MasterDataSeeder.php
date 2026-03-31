<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Criteria;
use App\Models\Performance;
use App\Models\Period;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $timestamp = now();

            $users = [
                [
                    'name' => 'Super Admin',
                    'email' => 'super-admin@gmail.com',
                    'role' => 'admin',
                    'password' => Hash::make('12345678'),
                ],
                [
                    'name' => 'User One',
                    'email' => 'user-one@gmail.com',
                    'role' => 'user',
                    'password' => Hash::make('12345678'),
                ],
                [
                    'name' => 'User Two',
                    'email' => 'user-two@gmail.com',
                    'role' => 'user',
                    'password' => Hash::make('12345678'),
                ],
                [
                    'name' => 'User Three',
                    'email' => 'user-three@gmail.com',
                    'role' => 'user',
                    'password' => Hash::make('12345678'),
                ],
            ];

            foreach ($users as $user) {
                User::updateOrCreate(
                    ['email' => $user['email']],
                    [
                        'name' => $user['name'],
                        'role' => $user['role'],
                        'password' => $user['password'],
                    ]
                );
            }

            $categoryData = [
                ['name' => 'pengelolahan produk dan konten', 'weight' => 0.18],
                ['name' => 'pengelolahan order', 'weight' => 0.25],
                ['name' => 'customer service', 'weight' => 0.22],
                ['name' => 'operasional marketplace', 'weight' => 0.25],
                ['name' => 'target penjualan dan pertumbuhan', 'weight' => 0.20],
            ];

            foreach ($categoryData as $category) {
                Category::updateOrCreate(
                    ['name' => $category['name']],
                    ['weight' => $category['weight']]
                );
            }

            $categoriesByName = Category::query()
                ->whereIn('name', array_column($categoryData, 'name'))
                ->pluck('id', 'name');

            $criteriaData = [
                ['category' => 'pengelolahan produk dan konten', 'name' => 'Ketepatan upload produk', 'weight' => 0.054, 'type' => 'cost'],
                ['category' => 'pengelolahan produk dan konten', 'name' => 'Kualitas konten', 'weight' => 0.054, 'type' => 'benefit'],
                ['category' => 'pengelolahan produk dan konten', 'name' => 'Kecepatan update stok dan harga', 'weight' => 0.036, 'type' => 'benefit'],
                ['category' => 'pengelolahan produk dan konten', 'name' => 'Ketepatan kategori & SEO listing', 'weight' => 0.036, 'type' => 'benefit'],

                ['category' => 'pengelolahan order', 'name' => 'Kecepatan memproses pesanan', 'weight' => 0.075, 'type' => 'benefit'],
                ['category' => 'pengelolahan order', 'name' => 'Ketepatan input resi / upload resi', 'weight' => 0.0625, 'type' => 'benefit'],
                ['category' => 'pengelolahan order', 'name' => 'Akurasi pesanan (minim kesalahan pengemasan)', 'weight' => 0.0625, 'type' => 'benefit'],
                ['category' => 'pengelolahan order', 'name' => 'Kepatuhan pada SOP pengiriman & platform', 'weight' => 0.050, 'type' => 'benefit'],

                ['category' => 'customer service', 'name' => 'Kecepatan respon chat', 'weight' => 0.066, 'type' => 'benefit'],
                ['category' => 'customer service', 'name' => 'Tingkat penyelesaian masalah', 'weight' => 0.066, 'type' => 'benefit'],
                ['category' => 'customer service', 'name' => 'Tingkat kepuasan pelanggan', 'weight' => 0.055, 'type' => 'benefit'],
                ['category' => 'customer service', 'name' => 'Keramahan dan kualitas komunikasi', 'weight' => 0.033, 'type' => 'benefit'],

                ['category' => 'operasional marketplace', 'name' => 'Ketepatan menjalankan program promosi', 'weight' => 0.0375, 'type' => 'benefit'],
                ['category' => 'operasional marketplace', 'name' => 'Manajemen inventaris', 'weight' => 0.045, 'type' => 'benefit'],
                ['category' => 'operasional marketplace', 'name' => 'Kepatuhan aturan marketplace', 'weight' => 0.0375, 'type' => 'benefit'],
                ['category' => 'operasional marketplace', 'name' => 'Konsistensi mengikuti timeline harian & mingguan', 'weight' => 0.030, 'type' => 'benefit'],

                ['category' => 'target penjualan dan pertumbuhan', 'name' => 'Ketepatan menjalankan program promosi', 'weight' => 0.080, 'type' => 'benefit'],
                ['category' => 'target penjualan dan pertumbuhan', 'name' => 'Kepatuhan aturan marketplace', 'weight' => 0.080, 'type' => 'benefit'],
            ];

            foreach ($criteriaData as $criteria) {
                Criteria::updateOrCreate(
                    [
                        'category_id' => $categoriesByName[$criteria['category']],
                        'name' => $criteria['name'],
                    ],
                    [
                        'weight' => $criteria['weight'],
                        'type' => $criteria['type'],
                    ]
                );
            }

            $periodData = [
                ['period_name' => '2024 Q1', 'is_finalized' => true],
                ['period_name' => '2024 Q2', 'is_finalized' => true],
                ['period_name' => '2024 Q3', 'is_finalized' => true],
            ];

            foreach ($periodData as $period) {
                Period::updateOrCreate(
                    ['period_name' => $period['period_name']],
                    ['is_finalized' => $period['is_finalized']]
                );
            }

            $criteriaIds = Criteria::query()->pluck('id');
            $periodIds = Period::query()->pluck('id');
            $userIds = User::query()->where('role', 'user')->pluck('id');

            $performanceRows = [];
            foreach ($userIds as $userId) {
                foreach ($periodIds as $periodId) {
                    foreach ($criteriaIds as $criteriaId) {
                        $performanceRows[] = [
                            'user_id' => $userId,
                            'criteria_id' => $criteriaId,
                            'period_id' => $periodId,
                            'score' => $this->generateScore($userId, $criteriaId, $periodId),
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ];
                    }
                }
            }

            Performance::upsert(
                $performanceRows,
                ['user_id', 'criteria_id', 'period_id'],
                ['score', 'updated_at']
            );
        });
    }

    private function generateScore(int $userId, int $criteriaId, int $periodId): float
    {
        $base = 65 + (($userId * 7) % 12) + (($criteriaId * 3) % 10) + (($periodId * 5) % 8);

        return round(min($base, 99), 2);
    }
}
