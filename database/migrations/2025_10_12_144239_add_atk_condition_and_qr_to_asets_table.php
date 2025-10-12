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
        Schema::table('asets', function (Blueprint $table) {
            // Fitur 1: ATK dan Expired Date
            $table->boolean('is_atk')->default(false)->after('lokasi');
            $table->date('expired_date')->nullable()->after('is_atk');
            
            // Fitur 2: Kondisi Barang
            // Opsi: 'Baik', 'Kurang Baik', 'Rusak'
            $table->string('kondisi_barang')->default('Baik')->after('expired_date'); 
            
            // Fitur 3: QR Code (LaraZeus)
            $table->string('qr_code')->nullable()->after('harga'); // URL/Data QR
            $table->text('qr_options')->nullable()->after('qr_code'); // Opsi Desain QR
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asets', function (Blueprint $table) {
            $table->dropColumn(['is_atk', 'expired_date', 'kondisi_barang', 'qr_code', 'qr_options']);
        });
    }
};
