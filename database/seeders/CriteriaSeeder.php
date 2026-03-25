<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Criteria;

class CriteriaSeeder extends Seeder
{

    // 1 - pengolahan produk dan konten (0.18)
    // - Ketepatan upload produk (judul, deskripsi, harga, variasi) - 0.054
    // - Kualitas konten (foto, copywriting, kelengkapan atribut) - 0.054
    // - Kecepatan update stok dan harga - 0.036
    // - Ketepatan kategori & SEO listing - 0.036
    
    // 2 - pengolahan order (0.25)
    // - Kecepatan memproses pesanan (SLA order to process) - 0.075
    // - Ketepatan input resi / upload resi - 0.0625
    // - Akurasi pesanan (minim kesalahan pengemasan) - 0.0625
    // - Kepatuhan pada SOP pengiriman & platform - 0.050

    // 3 - customer service (0.22)
    // - Kecepatan respon chat (chat response time) - 0.066
    // - Tingkat penyelesaian masalah (dispute & komplain) - 0.066
    // - Tingkat kepuasan pelanggan (rating toko / review) - 0.055
    // - Keramahan dan kualitas komunikasi - 0.033

    // 4 - operasional marketplace (0.25)
    // - Ketepatan menjalankan program promosi (flash sale, campaign, voucher) - 0.0375
    // - Manajemen inventaris (mencegah stok minus) - 0.045
    // - Kepatuhan aturan marketplace (hindari pelanggaran, penalti) - 0.0375 
    // - Konsistensi mengikuti timeline harian & mingguan - 0.030

    // 5 - target penjualan dan pertumbuhan (0.20)
    // - Pencapaian target penjualan harian/ bulanan (bila admin ikut bertanggung jawab) - 0.120
    // - Kontribusi terhadap peningkatan traffic & conversion rate - 0.080


    public function run(): void
    {
        // PENGOLAHAN PRODUK DAN KONTEN
        Criteria::insert([
            [
                'category_id' => 1,
                'name' => 'Ketepatan upload produk',
                'weight' => 0.054,
                'type' => 'cost'
            ],
            [
                'category_id' => 1,
                'name' => 'Kualitas konten',
                'weight' => 0.054,
                'type' => 'benefit'
            ],
            [
                'category_id' => 1,
                'name' => 'Kecepatan update stok dan harga',
                'weight' => 0.036,
                'type' => 'benefit'
            ],
            [
                'category_id' => 1,
                'name' => 'Ketepatan kategori & SEO listing',
                'weight' => 0.036,
                'type' => 'benefit'
            ]
        ]);

        // PENGOLAHAN ORDER
        Criteria::insert([
            [
                'category_id' => 2,
                'name' => 'Kecepatan memproses pesanan',
                'weight' => 0.075,
                'type' => 'benefit'
            ],
            [
                'category_id' => 2,
                'name' => 'Ketepatan input resi / upload resi',
                'weight' => 0.0625,
                'type' => 'benefit'
            ],
            [
                'category_id' => 2,
                'name' => 'Akurasi pesanan (minim kesalahan pengemasan)',
                'weight' => 0.0625,
                'type' => 'benefit'
            ],
            [
                'category_id' => 2,
                'name' => 'Kepatuhan pada SOP pengiriman & platform',
                'weight' => 0.050,
                'type' => 'benefit'
            ],
        ]);

        // CUSTOMER SERVICE
        Criteria::insert([
            [
                'category_id' => 3,
                'name' => 'Kecepatan respon chat',
                'weight' => 0.066,
                'type' => 'benefit'
            ],
            [
                'category_id' => 3,
                'name' => 'Tingkat penyelesaian masalah',
                'weight' => 0.066,
                'type' => 'benefit'
            ],
            [
                'category_id' => 3,
                'name' => 'Tingkat kepuasan pelanggan',
                'weight' => 0.055,
                'type' => 'benefit'
            ],
            [
                'category_id' => 3,
                'name' => 'Keramahan dan kualitas komunikasi',
                'weight' => 0.033,
                'type' => 'benefit'
            ]
        ]);

        // OPERASIONAL MARKETPLACE
        Criteria::insert([
            [
                'category_id' => 4,
                'name' => 'Ketepatan menjalankan program promosi',
                'weight' => 0.0375,
                'type' => 'benefit'
            ],
            [
                'category_id' => 4,
                'name' => 'Manajemen inventaris',
                'weight' => 0.045,
                'type' => 'benefit'
            ],
            [
                'category_id' => 4,
                'name' => 'Kepatuhan aturan marketplace',
                'weight' => 0.0375,
                'type' => 'benefit'
            ],
            [
                'category_id' => 4,
                'name' => 'Konsistensi mengikuti timeline harian & mingguan',
                'weight' => 0.030,
                'type' => 'benefit'
            ]
        ]);

        // PENINGKATAN TRAFFIC & CONVERSION RATE
        Criteria::insert([
            [
                'category_id' => 5,
                'name' => 'Ketepatan menjalankan program promosi',
                'weight' => 0.080,
                'type' => 'benefit'
            ],
            [
                'category_id' => 5,
                'name' => 'Kepatuhan aturan marketplace',
                'weight' => 0.080,
                'type' => 'benefit'
            ],
        ]);
    }
}
