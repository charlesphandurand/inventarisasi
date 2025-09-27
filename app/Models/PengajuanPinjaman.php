<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Aset;
use App\Models\RiwayatAset; // Tambahkan import untuk RiwayatAset

class PengajuanPinjaman extends Model
{
    use HasFactory;

    protected $table = 'pengajuan_pinjaman'; 
    protected $fillable = [
        'aset_id',
        'lokasi_sebelum', // Kolom penting untuk pengembalian
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

        // Event ketika record akan dihapus
        static::deleting(function ($pengajuan) {
            DB::transaction(function () use ($pengajuan) {
                // Kembalikan stok aset hanya jika pengajuan disetujui (disetujui/dikembalikan/ditolak tidak perlu dicek karena ini menangani PENGHAPUSAN data)
                // Jika statusnya 'disetujui' dan dihapus, stok harus dikembalikan.
                if ($pengajuan->status === 'disetujui' && $pengajuan->aset_id && $pengajuan->jumlah_pinjam > 0) {
                    $aset = Aset::find($pengajuan->aset_id);

                    if ($aset) {
                        $stokSebelum = $aset->jumlah_barang;
                        $lokasiAsetSebelum = $aset->lokasi;

                        // Gunakan withoutEvents agar tidak memicu log 'penambahan' ganda
                        $aset->withoutEvents(function () use ($aset, $pengajuan) {
                            $aset->increment('jumlah_barang', $pengajuan->jumlah_pinjam);
                            // Kembalikan lokasi aset ke lokasi sebelum dipinjam
                            if ($pengajuan->lokasi_sebelum) {
                                $aset->update(['lokasi' => $pengajuan->lokasi_sebelum]);
                            }
                        });
                        
                        // log
                        try {
                            RiwayatAset::create([
                                'aset_id' => $aset->id,
                                'user_id' => Auth::check() ? Auth::id() : null, // User yang menghapus (bisa admin)
                                'tipe' => 'pinjam_dihapus',
                                'jumlah_perubahan' => $pengajuan->jumlah_pinjam,
                                'stok_sebelum' => $stokSebelum,
                                'stok_sesudah' => $stokSebelum + $pengajuan->jumlah_pinjam,
                                'lokasi_sebelum' => $lokasiAsetSebelum,
                                'lokasi_sesudah' => $pengajuan->lokasi_sebelum ?? $lokasiAsetSebelum,
                                'keterangan' => 'Pengembalian stok dan lokasi karena pengajuan disetujui Dihapus',
                            ]);
                        } catch (\Throwable $e) {
                            // Opsional: log error jika RiwayatAset gagal dibuat
                        }
                    }
                }
            });
        });

        // Event ketika record diupdate (untuk menangani perubahan manual via Edit Form)
        static::updated(function ($pengajuan) {
            $oldStatus = $pengajuan->getOriginal('status');
            $newStatus = $pengajuan->status;
            $oldJumlah = (int) $pengajuan->getOriginal('jumlah_pinjam');
            $newJumlah = (int) $pengajuan->jumlah_pinjam;
            $oldAsetId = $pengajuan->getOriginal('aset_id');
            $newAsetId = $pengajuan->aset_id;

            // Jika tidak ada perubahan status, jumlah pinjam, atau aset_id, hentikan.
            if ($oldStatus === $newStatus && $oldJumlah === $newJumlah && $oldAsetId === $newAsetId) {
                return;
            }

            DB::transaction(function () use ($pengajuan, $oldStatus, $newStatus, $oldJumlah, $newJumlah, $oldAsetId, $newAsetId) {
                // A. Undo Stok Lama jika status berubah dari 'disetujui'
                if ($oldStatus === 'disetujui' && in_array($newStatus, ['ditolak', 'diajukan', 'dikembalikan', '']) && $oldAsetId && $oldJumlah > 0) {
                    $aset = Aset::find($oldAsetId);
                    if ($aset) {
                        $aset->withoutEvents(function () use ($aset, $oldJumlah, $pengajuan) {
                            $aset->increment('jumlah_barang', $oldJumlah);
                            // Kembalikan lokasi ke lokasi sebelum dipinjam yang tersimpan di record pengajuan
                            if ($pengajuan->lokasi_sebelum) {
                                $aset->update(['lokasi' => $pengajuan->lokasi_sebelum]);
                            }
                        });
                        // Opsional: Log riwayat pengembalian stok karena pembatalan/pengembalian manual via edit
                        // (Biasanya sudah ditangani oleh Aksi Tabel Dikembalikan, ini hanya untuk Edit form)
                    }
                }

                // B. Kurangi Stok Baru jika status berubah ke 'disetujui'
                if ($newStatus === 'disetujui' && $newAsetId && $newJumlah > 0) {
                    $asetBaru = Aset::find($newAsetId);
                    
                    if (!$asetBaru || $asetBaru->jumlah_barang < $newJumlah) {
                        // Jika stok tidak cukup, seharusnya validasi UI Filament sudah menangani, tapi kita cegah stok negatif
                        return; 
                    }
                    
                    $lokasiSebelum = $asetBaru->lokasi;
                    $peminjamNama = $pengajuan->user?->name ?? 'Peminjam';

                    // Hanya kurangi stok jika statusnya benar-benar berubah menjadi 'disetujui' DAN BUKAN 'disetujui' sebelumnya.
                    if ($oldStatus !== 'disetujui') {
                        $asetBaru->withoutEvents(function () use ($asetBaru, $newJumlah, $peminjamNama) {
                            $asetBaru->decrement('jumlah_barang', $newJumlah);
                            $asetBaru->update(['lokasi' => $peminjamNama]);
                        });

                        // Wajib simpan lokasi_sebelum saat pertama kali disetujui/disetujui via edit
                        $pengajuan->forceFill(['lokasi_sebelum' => $lokasiSebelum])->saveQuietly();
                        
                        // Opsional: Log approve manual via edit
                    } elseif ($oldStatus === 'disetujui' && ($oldAsetId !== $newAsetId || $oldJumlah !== $newJumlah)) {
                        // C. Penyesuaian stok saat status tetap 'disetujui' tapi jumlah/aset berubah (misalnya dari 5 ke 3)
                        
                        // 1. Kembalikan stok aset lama (jika aset_id berubah)
                        if ($oldAsetId !== $newAsetId && $oldAsetId && $oldJumlah > 0) {
                            $oldAset = Aset::find($oldAsetId);
                            if ($oldAset) {
                                $oldAset->withoutEvents(fn () => $oldAset->increment('jumlah_barang', $oldJumlah));
                            }
                        }

                        // 2. Sesuaikan stok pada aset baru/lama (jika hanya jumlah berubah)
                        $diff = $newJumlah - $oldJumlah;
                        $aset = Aset::find($newAsetId);
                        
                        if ($aset) {
                            $stokAsetSebelum = $aset->jumlah_barang;
                            
                            $aset->withoutEvents(function () use ($aset, $diff, $newJumlah, $stokAsetSebelum) {
                                if ($diff > 0) { // Penambahan jumlah pinjam (kurangi stok)
                                    if ($aset->jumlah_barang >= $diff) {
                                        $aset->decrement('jumlah_barang', $diff);
                                    } else {
                                        // Gagal karena stok tidak cukup
                                        throw new \Exception("Stok tidak cukup untuk menambah pinjaman ({$diff}). Stok tersisa: {$stokAsetSebelum}");
                                    }
                                } elseif ($diff < 0) { // Pengurangan jumlah pinjam (tambah stok)
                                    $aset->increment('jumlah_barang', abs($diff));
                                }
                                // Catatan: Lokasi tidak berubah, tetap nama peminjam
                            });
                            // Opsional: Log penyesuaian stok
                        }
                    }

                    // set admin_id & tanggal_approval bila kosong (saat approval manual via edit)
                    $updates = [];
                    if (empty($pengajuan->admin_id)) {
                        $updates['admin_id'] = Auth::id();
                    }
                    if (empty($pengajuan->tanggal_approval)) {
                        $updates['tanggal_approval'] = now()->setTimezone(config('app.timezone'));
                    }
                    if (!empty($updates)) {
                        $pengajuan->forceFill($updates)->saveQuietly();
                    }
                }
            });
        });
    }

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
