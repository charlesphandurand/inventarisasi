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
        Schema::table('pengajuan_pinjaman', function (Blueprint $table) {
            // Kolom dari migrasi Anda sebelumnya
            $table->string('lokasi_sebelum')->nullable()->after('aset_id');

            // Kolom baru yang dibutuhkan untuk melacak pengembalian parsial (memperbaiki error SQL)
            $table->unsignedInteger('jumlah_dikembalikan')->default(0)->after('jumlah_pinjam');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pengajuan_pinjaman', function (Blueprint $table) {
            $table->dropColumn('lokasi_sebelum');
            $table->dropColumn('jumlah_dikembalikan');
        });
    }
};
