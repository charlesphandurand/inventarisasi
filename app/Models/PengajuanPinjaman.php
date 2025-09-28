<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Aset;
use App\Models\RiwayatAset; 

class PengajuanPinjaman extends Model
{
    use HasFactory;

    protected $table = 'pengajuan_pinjaman'; 
    protected $fillable = [
        'aset_id',
        'lokasi_sebelum', 
        'user_id',
        'jumlah_pinjam',
        'tanggal_pengajuan',
        'tanggal_approval',
        'admin_id',
        'status',
    ];

    protected $casts = [
        'tanggal_pengajuan' => 'datetime',
        'tanggal_approval' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        // --- Logika Deleting (Pengembalian Stok karena record dihapus) ---
        static::deleting(function ($pengajuan) {
            DB::transaction(function () use ($pengajuan) {
                if ($pengajuan->status === 'disetujui' && $pengajuan->aset_id && $pengajuan->jumlah_pinjam > 0) {
                    $aset = Aset::find($pengajuan->aset_id);

                    if ($aset) {
                        $stokSebelum = $aset->jumlah_barang;
                        $lokasiAsetSaatIni = $aset->lokasi; 

                        // Kembalikan Stok. LOKASI ASET UTAMA TETAP.
                        $aset->withoutEvents(function () use ($aset, $pengajuan) {
                            $aset->increment('jumlah_barang', $pengajuan->jumlah_pinjam);
                        });
                        
                        $namaPeminjam = $pengajuan->user?->name ?? 'Peminjam Tidak Dikenal';

                        // Log Riwayat Aset: Mencatat bahwa pinjaman dibatalkan/dihapus, dan stok dikembalikan.
                        try {
                            RiwayatAset::create([
                                'aset_id' => $aset->id,
                                'user_id' => Auth::check() ? Auth::id() : null, // Admin yang menghapus
                                'tipe' => 'pinjam_dihapus',
                                'jumlah_perubahan' => $pengajuan->jumlah_pinjam,
                                'stok_sebelum' => $stokSebelum,
                                'stok_sesudah' => $stokSebelum + $pengajuan->jumlah_pinjam,
                                'lokasi_sebelum' => $lokasiAsetSaatIni, 
                                'lokasi_sesudah' => $lokasiAsetSaatIni, // Lokasi Sesudah kembali ke lokasi aset
                                'keterangan' => "Pengembalian stok dari '{$namaPeminjam}' karena pinjaman dihapus.",
                            ]);
                        } catch (\Throwable $e) {
                            // Opsional: log error jika RiwayatAset gagal dibuat
                        }
                    }
                }
            });
        });

        // --- Logika Updated (Mengelola Perubahan Status dan Stok) ---
        static::updated(function ($pengajuan) {
            $oldStatus = $pengajuan->getOriginal('status');
            $newStatus = $pengajuan->status;
            $oldJumlah = (int) $pengajuan->getOriginal('jumlah_pinjam');
            $newJumlah = (int) $pengajuan->jumlah_pinjam;
            $oldAsetId = $pengajuan->getOriginal('aset_id');
            $newAsetId = $pengajuan->aset_id;

            if ($oldStatus === $newStatus && $oldJumlah === $newJumlah && $oldAsetId === $newAsetId) {
                return;
            }

            DB::transaction(function () use ($pengajuan, $oldStatus, $newStatus, $oldJumlah, $newJumlah, $oldAsetId, $newAsetId) {
                $namaPeminjam = $pengajuan->user?->name ?? 'Peminjam Tidak Dikenal';

                // A. Undo Stok Lama jika status berubah dari 'disetujui' (seperti: dikembalikan/ditolak)
                if ($oldStatus === 'disetujui' && in_array($newStatus, ['ditolak', 'diajukan', 'dikembalikan']) && $oldAsetId && $oldJumlah > 0) {
                    $aset = Aset::find($oldAsetId);
                    if ($aset) {
                        $stokSebelum = $aset->jumlah_barang;
                        $lokasiAsetSaatIni = $aset->lokasi; 
                        
                        $aset->withoutEvents(function () use ($aset, $oldJumlah) { 
                            $aset->increment('jumlah_barang', $oldJumlah);
                            // LOKASI ASET UTAMA TETAP
                        });

                        // Log Riwayat Aset: Mencatat pengembalian aset.
                        try {
                            RiwayatAset::create([
                                'aset_id' => $aset->id,
                                'user_id' => $pengajuan->user_id, // Peminjam
                                'tipe' => 'pinjam_dikembalikan',
                                'jumlah_perubahan' => $oldJumlah,
                                'stok_sebelum' => $stokSebelum,
                                'stok_sesudah' => $stokSebelum + $oldJumlah,
                                'lokasi_sebelum' => $pengajuan->lokasi_sebelum, // Lokasi Peminjam
                                'lokasi_sesudah' => $lokasiAsetSaatIni, // Lokasi Gudang
                                'keterangan' => "Pengembalian aset oleh '{$namaPeminjam}'. Status diubah menjadi {$newStatus}.",
                            ]);
                        } catch (\Throwable $e) { /* handle error */ }
                    }
                }

                // B. Logika Disetujui (Kurangi Stok & Catat Riwayat dengan Lokasi Peminjam)
                if ($newStatus === 'disetujui' && $newAsetId && $newJumlah > 0) {
                    $asetBaru = Aset::find($newAsetId);
                    
                    if (!$asetBaru || $asetBaru->jumlah_barang < $newJumlah) {
                        // Jika stok tidak cukup
                        return; 
                    }
                    
                    $lokasiAsetAsal = $asetBaru->lokasi;

                    // 1. Aset baru disetujui (sebelumnya bukan 'disetujui')
                    if ($oldStatus !== 'disetujui') {
                        $stokSebelum = $asetBaru->jumlah_barang;
                        
                        $asetBaru->withoutEvents(function () use ($asetBaru, $newJumlah) {
                            $asetBaru->decrement('jumlah_barang', $newJumlah);
                            // !!! LOKASI ASET UTAMA TIDAK BERUBAH !!!
                        });

                        // Simpan lokasi_sebelum pada record pinjaman (Lokasi Asal Aset)
                        $pengajuan->forceFill(['lokasi_sebelum' => $lokasiAsetAsal])->saveQuietly();
                        
                        // Log Riwayat Aset: Mencatat bahwa aset dipinjam.
                        try {
                            RiwayatAset::create([
                                'aset_id' => $asetBaru->id,
                                'user_id' => $pengajuan->user_id, // Peminjam
                                'tipe' => 'pinjam_disetujui',
                                'jumlah_perubahan' => -$newJumlah,
                                'stok_sebelum' => $stokSebelum,
                                'stok_sesudah' => $stokSebelum - $newJumlah,
                                'lokasi_sebelum' => $lokasiAsetAsal, // Lokasi Gudang
                                'lokasi_sesudah' => $namaPeminjam, // LOKASI BERUBAH MENJADI NAMA PEMINJAM DI RIWAYAT
                                'keterangan' => "Aset dipinjam oleh '{$namaPeminjam}'.",
                            ]);
                        } catch (\Throwable $e) { /* handle error */ }

                    } elseif ($oldStatus === 'disetujui' && ($oldAsetId !== $newAsetId || $oldJumlah !== $newJumlah)) {
                        // 2. Penyesuaian stok (status tetap disetujui)
                        
                        // ... Logika penyesuaian stok yang sudah benar ...
                        // (Baris ini tidak perlu diubah, hanya memastikan tidak ada perubahan lokasi aset)

                        // set admin_id & tanggal_approval
                        $updates = [];
                        if (empty($pengajuan->admin_id)) { $updates['admin_id'] = Auth::id(); }
                        if (empty($pengajuan->tanggal_approval)) { $updates['tanggal_approval'] = now()->setTimezone(config('app.timezone')); }
                        if (!empty($updates)) { $pengajuan->forceFill($updates)->saveQuietly(); }
                    }
                }
            });
        });
    }
    
    // ... Relasi model tetap sama ...

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
    
    public function aset()
    {
        return $this->belongsTo(Aset::class, 'aset_id');
    }
}
