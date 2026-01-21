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
        Schema::create('performances', function (Blueprint $table) {
            $table->id();

            // Relasi ke tabel users (Karyawan)
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Relasi ke tabel criterias
            $table->foreignId('criteria_id')->constrained('criterias')->onDelete('cascade');

            // Relasi ke tabel periods
            $table->foreignId('period_id')->constrained('periods')->onDelete('cascade');

            // Nilai mentah (score), contoh: 85.50
            $table->decimal('score', 8, 2);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performances');
    }
};
