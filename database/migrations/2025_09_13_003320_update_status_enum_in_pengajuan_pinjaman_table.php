<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // PERBAIKAN TOTAL: Menambahkan 'dikeluarkan' ke daftar ENUM.
        // Ini memastikan semua status yang digunakan di aplikasi sudah valid di database.
        DB::statement("ALTER TABLE pengajuan_pinjaman MODIFY COLUMN status ENUM('diajukan', 'disetujui', 'ditolak', 'dikembalikan', 'dikeluarkan') DEFAULT 'diajukan'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Mengembalikan ENUM ke daftar sebelum penambahan ini
        DB::statement("ALTER TABLE pengajuan_pinjaman MODIFY COLUMN status ENUM('diajukan', 'disetujui', 'ditolak', 'dikembalikan') DEFAULT 'diajukan'");
    }
};
