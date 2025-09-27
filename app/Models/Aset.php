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
    ];

    public function pengajuanPinjaman()
    {
        return $this->hasMany(PengajuanPinjaman::class);
    }

    protected static function booted(): void
    {
        // Event saat aset baru dibuat
        static::created(function (Aset $aset) {
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

        // Event saat aset diupdate
        static::updated(function (Aset $aset) {
            $changes = $aset->getChanges();
            $original = $aset->getOriginal();

            // *** Field yang harus diabaikan dari logging umum ***
            $ignoredFields = ['nama_barang', 'nama_vendor', 'keterangan', 'updated_at', 'created_at'];

            // 1. Cek perubahan Stok
            $isStokChanged = array_key_exists('jumlah_barang', $changes) && $original['jumlah_barang'] !== $aset->jumlah_barang;
            
            // 2. Cek perubahan Lokasi
            $isLokasiChanged = array_key_exists('lokasi', $changes) && $original['lokasi'] !== $aset->lokasi;
            
            // 3. Cek perubahan Harga (konversi ke float untuk membandingkan nilai, bukan string/tipe)
            $isHargaChanged = array_key_exists('harga', $changes) && (float)($original['harga'] ?? 0) !== (float)$aset->harga;

            // *** KONDISI PENGHENTIAN/FILTER UTAMA ***
            // Hentikan eksekusi jika TIDAK ADA perubahan signifikan pada Stok, Lokasi, dan Harga,
            // dan perubahan HANYA pada field yang diabaikan.
            $hasSignificantChange = $isStokChanged || $isLokasiChanged || $isHargaChanged;

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
        });
    }
}
