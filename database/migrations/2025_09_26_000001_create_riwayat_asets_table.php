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
        Schema::create('riwayat_asets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aset_id')->constrained('asets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipe'); // create, update, penambahan, pengurangan, pinjam_disetujui, pinjam_dikembalikan, harga_update, lokasi_update
            $table->integer('jumlah_perubahan')->nullable(); // positif untuk penambahan, negatif untuk pengurangan
            $table->integer('stok_sebelum')->nullable();
            $table->integer('stok_sesudah')->nullable();
            $table->decimal('harga_sebelum', 15, 2)->nullable();
            $table->decimal('harga_sesudah', 15, 2)->nullable();
            $table->string('lokasi_sebelum')->nullable();
            $table->string('lokasi_sesudah')->nullable();
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riwayat_asets');
    }
};


