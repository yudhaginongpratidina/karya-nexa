<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('topsis_results', function (Blueprint $table) {
            $table->id();

            // Relasi ke tabel users
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Relasi ke tabel periods
            $table->foreignId('period_id')->constrained('periods')->onDelete('cascade');

            // Nilai preferensi (Ci*) biasanya antara 0 sampai 1
            // Menggunakan decimal(10, 8) agar presisi perhitungan sangat tinggi
            $table->decimal('preference_value', 10, 8);

            // Kolom untuk menyimpan peringkat
            $table->integer('rank')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('topsis_results');
    }
};
