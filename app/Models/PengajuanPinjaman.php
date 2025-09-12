<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Aset;

class PengajuanPinjaman extends Model
{
    use HasFactory;

    protected $table = 'pengajuan_pinjaman'; 
    protected $fillable = [
        'aset_id',
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
                // Kembalikan stok aset hanya jika pengajuan disetujui
                if ($pengajuan->status === 'disetujui' && $pengajuan->aset_id && $pengajuan->jumlah_pinjam > 0) {
                    $aset = Aset::find($pengajuan->aset_id);
                    if ($aset) {
                        $aset->increment('jumlah_barang', $pengajuan->jumlah_pinjam);
                    }
                }
            });
        });

        // Event ketika record diupdate
        static::updated(function ($pengajuan) {
            $oldStatus = $pengajuan->getOriginal('status');
            $newStatus = $pengajuan->status;
            $oldJumlah = (int) $pengajuan->getOriginal('jumlah_pinjam');
            $newJumlah = (int) $pengajuan->jumlah_pinjam;
            $oldAsetId = $pengajuan->getOriginal('aset_id');
            $newAsetId = $pengajuan->aset_id;

            DB::transaction(function () use ($pengajuan, $oldStatus, $newStatus, $oldJumlah, $newJumlah, $oldAsetId, $newAsetId) {
                // 1) Jika status berubah dari disetujui -> ditolak/diajukan: kembalikan stok lama
                if ($oldStatus === 'disetujui' && in_array($newStatus, ['ditolak', 'diajukan'])) {
                    if ($oldAsetId && $oldJumlah > 0) {
                        $aset = Aset::find($oldAsetId);
                        if ($aset) {
                            $aset->increment('jumlah_barang', $oldJumlah);
                        }
                    }
                }

                // 1b) Jika dari non-disetujui -> disetujui (approve via edit)
                if ($oldStatus !== 'disetujui' && $newStatus === 'disetujui') {
                    if ($newAsetId && $newJumlah > 0) {
                        $asetBaru = Aset::find($newAsetId);
                        if (!$asetBaru || $asetBaru->jumlah_barang < $newJumlah) {
                            // Tidak ada perubahan stok jika stok tidak cukup; validasi ditangani di layer UI
                            return;
                        }
                        $asetBaru->decrement('jumlah_barang', $newJumlah);
                    }

                    // set admin_id & tanggal_approval bila kosong
                    $updates = [];
                    if (empty($pengajuan->admin_id)) {
                        $updates['admin_id'] = \Illuminate\Support\Facades\Auth::id();
                    }
                    if (empty($pengajuan->tanggal_approval)) {
                        $updates['tanggal_approval'] = now()->setTimezone(config('app.timezone'));
                    }
                    if (!empty($updates)) {
                        $pengajuan->forceFill($updates)->saveQuietly();
                    }
                }

                // 2) Jika status tetap 'disetujui', maka sesuaikan stok jika jumlah/aset berubah
                if ($oldStatus === 'disetujui' && $newStatus === 'disetujui') {
                    if ($oldAsetId !== $newAsetId) {
                        // Kembalikan stok aset lama
                        if ($oldAsetId && $oldJumlah > 0) {
                            $oldAset = Aset::find($oldAsetId);
                            if ($oldAset) {
                                $oldAset->increment('jumlah_barang', $oldJumlah);
                            }
                        }
                        // Kurangi stok aset baru sesuai jumlah baru
                        if ($newAsetId && $newJumlah > 0) {
                            $newAset = Aset::find($newAsetId);
                            if ($newAset && $newAset->jumlah_barang >= $newJumlah) {
                                $newAset->decrement('jumlah_barang', $newJumlah);
                            }
                        }
                    } elseif ($oldJumlah !== $newJumlah && $newAsetId) {
                        $diff = $newJumlah - $oldJumlah;
                        $aset = Aset::find($newAsetId);
                        if ($aset) {
                            if ($diff > 0) {
                                if ($aset->jumlah_barang >= $diff) {
                                    $aset->decrement('jumlah_barang', $diff);
                                }
                            } elseif ($diff < 0) {
                                $aset->increment('jumlah_barang', abs($diff));
                            }
                        }
                    }
                }

                // 3) Jika status berubah ke 'disetujui' dari selain disetujui,
                // stok sudah dikurangi oleh aksi approve di tabel, jadi tidak perlu aksi tambahan di sini
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

    // Method helper untuk update manual jika diperlukan
    public function returnAsetStock()
    {
        if ($this->aset_id && $this->jumlah_pinjam > 0) {
            $aset = Aset::find($this->aset_id);
            if ($aset) {
                $aset->increment('jumlah_barang', $this->jumlah_pinjam);
            }
        }
    }

    public function reduceAsetStock()
    {
        if ($this->aset_id && $this->jumlah_pinjam > 0) {
            $aset = Aset::find($this->aset_id);
            if ($aset && $aset->jumlah_barang >= $this->jumlah_pinjam) {
                $aset->decrement('jumlah_barang', $this->jumlah_pinjam);
                return true;
            }
        }
        return false;
    }
}