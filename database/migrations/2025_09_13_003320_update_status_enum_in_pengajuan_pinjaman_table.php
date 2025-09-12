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
        // Update enum values untuk status di pengajuan_pinjaman
        DB::statement("ALTER TABLE pengajuan_pinjaman MODIFY COLUMN status ENUM('diajukan', 'disetujui', 'ditolak', 'dikembalikan') DEFAULT 'diajukan'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Kembalikan ke enum values sebelumnya
        DB::statement("ALTER TABLE pengajuan_pinjaman MODIFY COLUMN status ENUM('diajukan', 'disetujui', 'ditolak') DEFAULT 'diajukan'");
    }
};
