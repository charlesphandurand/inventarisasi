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
        Schema::create('asets', function (Blueprint $table) {
            $table->id();
            $table->string('nama_barang');
            $table->integer('jumlah_barang')->default(0);
            $table->integer('sisa_barang')->default(0);
            $table->integer('penambahan_barang')->default(0);
            $table->integer('pengajuan_barang')->default(0);
            $table->timestamps();
            $table->string('lokasi')->nullable();
            $table->string('atas_nama')->nullable();
            $table->string('keterangan')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asets');
    }
};
