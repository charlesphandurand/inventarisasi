<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Aset extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'nama_barang',
        'jumlah_barang',
        'lokasi',
        'keterangan',
        'nama_vendor',
        'harga',
        'is_atk', // Tambahan Baru
        'expired_date', // Tambahan Baru
        'kondisi_barang', // Tambahan Baru
        'qr_code', // Tambahan Baru
        'qr_options', // Tambahan Baru
    ];
    
    // Wajib: Cast qr_options ke array untuk LaraZeus Qr
    protected $casts = [
        'qr_options' => 'array',
        'is_atk' => 'boolean',
        'expired_date' => 'date',
    ];

    public function pengajuanPinjaman()
    {
        return $this->hasMany(PengajuanPinjaman::class);
    }

    protected static function booted(): void
    {
        // --- LOGIC QR CODE (FIX SCAN RESULT "qr_code":null) ---
        // Ketika Model baru selesai dibuat (ID sudah tersedia), kita generate QR code string.
        static::created(function (Aset $aset) {
            // 1. Logic untuk Auto-Generate QR Code (Tambahan untuk FIX)
            // Kita hanya mengisi qr_code jika memang masih kosong atau berisi string JSON error
            if (empty($aset->qr_code) || $aset->qr_code === '{"qr_options":null,"qr_code":null}') {
                
                // Gunakan URL yang merujuk ke halaman detail aset di Filament. 
                // Format URL umumnya: /dashboard/asets/{id}/edit
                $qrContent = url('/dashboard/asets/' . $aset->id . '/edit');
                
                // Isi atribut qr_code dengan URL yang valid
                $aset->qr_code = $qrContent;
                
                // Simpan perubahan ke database tanpa memicu event saving/updating lagi
                // Simpan secara terpisah dari logic Riwayat Aset
                $aset->saveQuietly(); 
            }

            // 2. Logic Riwayat Aset (Kode Lama Anda)
            try {
                \App\Models\RiwayatAset::create([
                    'aset_id' => $aset->id,
                    'user_id' => Auth::id(),
                    'tipe' => 'create',
                    'jumlah_perubahan' => $aset->jumlah_barang,
                    'stok_sebelum' => 0,
                    'stok_sesudah' => $aset->jumlah_barang,
                    'harga_sebelum' => null,
                    'harga_sesudah' => $aset->harga,
                    'lokasi_sebelum' => null,
                    'lokasi_sesudah' => $aset->lokasi,
                    'keterangan' => 'Pembuatan aset baru',
                ]);
            } catch (\Throwable $e) {
                // ...
            }
        });
        // --- AKHIR LOGIC QR CODE ---


        // Event saat aset diupdate (LOGIC LAMA ANDA)
        static::updated(function (Aset $aset) {
            $changes = $aset->getChanges();
            $original = $aset->getOriginal();

            // *** Field yang harus diabaikan dari logging umum ***
            $ignoredFields = ['nama_barang', 'nama_vendor', 'keterangan', 'updated_at', 'created_at', 'qr_code', 'qr_options'];

            // 1. Cek perubahan Stok
            $isStokChanged = array_key_exists('jumlah_barang', $changes) && $original['jumlah_barang'] !== $aset->jumlah_barang;
            
            // 2. Cek perubahan Lokasi
            $isLokasiChanged = array_key_exists('lokasi', $changes) && $original['lokasi'] !== $aset->lokasi;
            
            // 3. Cek perubahan Harga (konversi ke float untuk membandingkan nilai, bukan string/tipe)
            $isHargaChanged = array_key_exists('harga', $changes) && (float)($original['harga'] ?? 0) !== (float)$aset->harga;

            // 4. Cek perubahan Kondisi Barang (BARU)
            $isKondisiChanged = array_key_exists('kondisi_barang', $changes) && $original['kondisi_barang'] !== $aset->kondisi_barang;

            // 5. Cek perubahan Expired Date (BARU)
            // Membandingkan format string tanggal untuk amannya.
            $isExpiredDateChanged = array_key_exists('expired_date', $changes) && (string)($original['expired_date'] ?? '') !== (string)($aset->expired_date ?? '');

            // *** KONDISI PENGHENTIAN/FILTER UTAMA ***
            // Hentikan eksekusi jika TIDAK ADA perubahan signifikan pada Stok, Lokasi, dan Harga,
            // dan perubahan HANYA pada field yang diabaikan.
            $hasSignificantChange = $isStokChanged || $isLokasiChanged || $isHargaChanged || $isKondisiChanged || $isExpiredDateChanged;

            if (!$hasSignificantChange) {
                // Dapatkan list field yang benar-benar berubah (selain yang diabaikan)
                $nonIgnoredChanges = array_diff_key($changes, array_flip($ignoredFields));
                
                // Jika tidak ada perubahan signifikan (stok/lokasi/harga) DAN tidak ada perubahan field lain
                // selain nama/vendor/keterangan, maka kita return.
                if (empty($nonIgnoredChanges)) {
                    return; 
                }
            }
            // ************************************************
            
            // --- Logika Khusus untuk Field Krusial (Stok, Harga, Lokasi) ---

            // 1. Stok berubah (jumlah_barang)
            if ($isStokChanged) {
                $before = (int) ($original['jumlah_barang'] ?? 0);
                $after = (int) $aset->jumlah_barang;
                $diff = $after - $before;
                
                \App\Models\RiwayatAset::create([
                    'aset_id' => $aset->id,
                    'user_id' => Auth::id(),
                    'tipe' => $diff >= 0 ? 'penambahan' : 'pengurangan',
                    'jumlah_perubahan' => $diff,
                    'stok_sebelum' => $before,
                    'stok_sesudah' => $after,
                    'keterangan' => 'Perubahan stok aset',
                    'harga_sebelum' => null,
                    'harga_sesudah' => null,
                    'lokasi_sebelum' => null,
                    'lokasi_sesudah' => null,
                ]);
            }

            // 2. Harga berubah (harga)
            if ($isHargaChanged) {
                \App\Models\RiwayatAset::create([
                    'aset_id' => $aset->id,
                    'user_id' => Auth::id(),
                    'tipe' => 'harga_update',
                    'jumlah_perubahan' => null,
                    'stok_sebelum' => null,
                    'stok_sesudah' => null,
                    // Pastikan log menyimpan nilai yang di-cast ke string/number sesuai tipe database
                    'harga_sebelum' => $original['harga'] ?? null,
                    'harga_sesudah' => $aset->harga,
                    'lokasi_sebelum' => null,
                    'lokasi_sesudah' => null,
                    'keterangan' => 'Perubahan harga aset',
                ]);
            }

            // 3. Lokasi berubah (lokasi)
            if ($isLokasiChanged) {
                
                \App\Models\RiwayatAset::create([
                    'aset_id' => $aset->id,
                    'user_id' => Auth::id(),
                    'tipe' => 'lokasi_update',
                    'jumlah_perubahan' => null,
                    'stok_sebelum' => null,
                    'stok_sesudah' => null,
                    'harga_sebelum' => null,
                    'harga_sesudah' => null,
                    'lokasi_sebelum' => $original['lokasi'] ?? null,
                    'lokasi_sesudah' => $aset->lokasi,
                    'keterangan' => 'Perubahan lokasi aset',
                ]);
            }

            // 4. Kondisi Barang berubah (BARU)
            if ($isKondisiChanged) {
                \App\Models\RiwayatAset::create([
                    'aset_id' => $aset->id,
                    'user_id' => Auth::id(),
                    'tipe' => 'kondisi_update',
                    'keterangan' => "Perubahan kondisi: {$original['kondisi_barang']} -> {$aset->kondisi_barang}",
                ]);
            }

            // 5. Expired Date berubah (BARU)
            if ($isExpiredDateChanged) {
                $keterangan = $aset->expired_date 
                    ? "Expired date diperbarui menjadi: " . $aset->expired_date->format('d/m/Y')
                    : "Expired date dikosongkan";

                \App\Models\RiwayatAset::create([
                    'aset_id' => $aset->id,
                    'user_id' => Auth::id(),
                    'tipe' => 'exp_date_update',
                    'keterangan' => $keterangan,
                ]);
            }

        });
    }
}
