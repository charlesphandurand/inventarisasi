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
        Schema::create('pengajuan_pinjaman', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aset_id')->constrained('asets'); // Relasi ke tabel aset
            $table->integer('jumlah_pinjam');
            $table->timestamp('tanggal_pengajuan');
            $table->timestamp('tanggal_approval')->nullable();
            $table->foreignId('admin_id')->nullable()->constrained('users'); // Relasi ke admin yang approve
            $table->enum('status', ['diajukan', 'disetujui', 'ditolak'])->default('diajukan');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengajuan_pinjaman');
    }
};
