<?php

namespace App\Filament\Resources\PermintaanBarang\Pages;

use App\Filament\Resources\PermintaanBarang\PermintaanBarangResource;
use App\Models\Aset; 
use App\Models\RiwayatAset; 
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon; 
use Filament\Notifications\Notification; 
use Illuminate\Support\Facades\DB; 
use Illuminate\Auth\Access\AuthorizationException; 

class EditPermintaanBarang extends EditRecord
{
    protected static string $resource = PermintaanBarangResource::class;

    // Flag internal untuk mencegah eksekusi ganda di afterSave
    protected bool $skipAfterSave = false;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Override handler update agar logika persis seperti aksi tabel.
     * Menggunakan handler di Resource dan menandai untuk skip afterSave.
     */
    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        $this->skipAfterSave = true;
        $updated = PermintaanBarangResource::handleRecordUpdate($record, $data);
        return $updated;
    }

    /**
     * Override otorisasi untuk mengontrol siapa yang boleh mengakses halaman Edit.
     */
    protected function authorizeRecordAccess(): void
    {
        $record = $this->getRecord();
        $user = Auth::user();
        
        // Cek peran utama
        $hasAdminRole = $user?->hasAnyRole(['approver', 'maker']) ?? false;
        $isRequester = $record->user_id === $user->id;

        if (!$user) {
            throw new AuthorizationException('Anda harus login untuk mengakses halaman ini.');
        }

        // Jika status sudah dikeluarkan, blokir akses untuk SIAPAPUN.
        if ($record->status === 'dikeluarkan') {
            throw new AuthorizationException('Permintaan yang sudah dikeluarkan tidak dapat diubah.');
        }
        
        // KASUS 1: APPROVER atau MAKER SELALU DIZINKAN (karena sudah lolos cek 'dikeluarkan' di atas).
        if ($hasAdminRole) {
            parent::authorizeRecordAccess(); 
            return;
        }

        // KASUS 2: USER BIASA (NON-ADMIN)
        // Jika dia adalah pembuat record (Requester) DAN statusnya 'diajukan', diizinkan.
        if ($isRequester && $record->status === 'diajukan') {
            parent::authorizeRecordAccess();
            return;
        }
        
        // KASUS 3: SEMUA KASUS LAIN DIBLOKIR.
        throw new AuthorizationException('Anda tidak diizinkan untuk mengakses halaman ini.');
    }

    /**
     * Hook yang dijalankan setelah record berhasil disimpan (di-update) dari Formulir Edit.
     * Logika untuk update status dan stok.
     */
    protected function afterSave(): void
    {
        // Jika update sudah ditangani oleh Resource handler, lewati hook ini
        if ($this->skipAfterSave) {
            return;
        }
        $record = $this->getRecord();
        $user = Auth::user();
        
        // Cek apakah pengguna memiliki peran admin/approver/maker
        $isAdmin = $user?->hasAnyRole(['maker', 'approver']) ?? false;

        // Jika tidak ada user terautentikasi atau status tidak berubah, hentikan proses.
        if (!$user || $record->getOriginal('status') === $record->status) {
              return;
        }

        // Ambil status sebelum dan sesudah disimpan.
        $oldStatus = $record->getOriginal('status'); 
        $newStatus = $record->status;         

        // --- Logika Pencatatan Verifikasi/Approval/Pengeluaran ---
        
        if ($oldStatus !== $newStatus) {
            // Perubahan status hanya boleh dilakukan oleh Admin/Approver
            if (!$isAdmin) {
                // Kembalikan status ke semula dan berikan notifikasi
                $record->forceFill(['status' => $oldStatus])->saveQuietly();
                Notification::make()->title('Gagal Update')->body('Anda tidak memiliki hak untuk mengubah status permintaan.')->danger()->send();
                return;
            }

            // A. TANGANI TRANSISI KE STATUS 'dikeluarkan' (ATOMIC: Approval, Stok, Riwayat)
            if ($newStatus === 'dikeluarkan') {
                
                // Gunakan transaksi untuk menjamin atomisitas dari Approval, Stok, dan Riwayat
                // Jika terjadi error di dalam closure ini, semua akan di-rollback.
                DB::transaction(function () use ($record, $user, $oldStatus) {
                    $aset = Aset::find($record->aset_id);

                    if (!$aset) {
                        Notification::make()->title('Error')->body('Aset (ATK) tidak ditemukan saat mencoba mengeluarkan barang.')->danger()->send();
                        return; // Keluar dari transaksi
                    }
                    
                    // Lakukan persiapan data
                    $jumlahKeluar = $record->jumlah_pinjam;
                    $stokSebelum = $aset->jumlah_barang;
                    $stokSesudah = $stokSebelum - $jumlahKeluar;
                    $lokasiGudang = $aset->lokasi; 
                    $peminjamNama = $record->user?->name ?? 'Pemohon';

                    if ($stokSesudah < 0) {
                        // Jika stok tidak cukup, batalkan perubahan status Permintaan Barang dan berikan notifikasi.
                        $record->forceFill(['status' => $oldStatus])->saveQuietly();
                        Notification::make()->title('Gagal Pengeluaran')->body("Stok {$aset->nama_barang} tidak cukup. Stok: {$stokSebelum}. Permintaan: {$jumlahKeluar}")->danger()->send();
                        return; // Keluar dari transaksi
                    }
                    
                    // 1. ATOMIC STOCK UPDATE (Menggunakan Query Builder & Optimistic Locking)
                    $updated = DB::table('asets')
                                 ->where('id', $aset->id)
                                 ->where('jumlah_barang', $stokSebelum) // Pengecekan konsistensi (race condition)
                                 ->update(['jumlah_barang' => $stokSesudah]);
                    
                    if (!$updated) {
                         // Jika gagal update (misalnya karena Race Condition), batalkan perubahan status.
                         $record->forceFill(['status' => $oldStatus])->saveQuietly();
                         Notification::make()->title('Gagal Pengeluaran')->body('Terjadi masalah konsistensi data stok. Status Permintaan dikembalikan. Silakan coba lagi.')->danger()->send();
                         return; // Keluar dari transaksi
                    }

                    // 2. MUAT ULANG MODEL ASET agar data stok terbaru diambil dari DB untuk RiwayatAset
                    // Penting: Memastikan data Aset terbaru diambil untuk pencatatan riwayat yang akurat.
                    $aset->refresh();
                    $stokSesudahReff = $aset->jumlah_barang;

                    // 3. Update Permintaan Barang dengan data approval dan pengeluaran (SEMUA DALAM TRANSACTION)
                    $record->forceFill([
                        'admin_id' => $user->id,
                        'tanggal_approval' => Carbon::now()->setTimezone(config('app.timezone')),
                        'jumlah_dikembalikan' => $jumlahKeluar, 
                        'lokasi_sebelum' => $lokasiGudang, 
                    ])->saveQuietly(); 
                    
                    // 4. Catat RIWAYAT PENGELUARAN BARANG ATK
                    RiwayatAset::create([
                        'aset_id' => $aset->id,
                        'user_id' => $user->id, // Admin yang mengeluarkan
                        'tipe' => 'permintaan_atk_dikeluarkan', 
                        'jumlah_perubahan' => -$jumlahKeluar,
                        'stok_sebelum' => $stokSebelum,
                        'stok_sesudah' => $stokSesudahReff, // Gunakan nilai setelah update
                        'lokasi_sebelum' => $lokasiGudang, 
                        'lokasi_sesudah' => $peminjamNama . ' (Diterima)', 
                        'keterangan' => 'Permintaan ATK disetujui dan dikeluarkan melalui Edit Manual oleh ' . $user->name . '.',
                    ]);
                    
                    Notification::make()->title('Pengeluaran Berhasil')->body("Stok {$aset->nama_barang} berkurang sebanyak {$jumlahKeluar}. Stok tersisa: {$stokSesudahReff}. Riwayat Dicatat.")->success()->send();
                });
            }

            // B. TANGANI TRANSISI KE STATUS 'diverifikasi' atau 'ditolak' (Approval/Tolak Hanya)
            elseif (in_array($newStatus, ['diverifikasi', 'ditolak'])) {
                
                // Kunci: Selalu set admin_id dan tanggal_approval
                $record->forceFill([
                    'admin_id' => $user->id,
                    'tanggal_approval' => Carbon::now()->setTimezone(config('app.timezone')),
                ])->saveQuietly(); 
                
                $title = "Permintaan Diperbarui: Status '{$newStatus}'";
                Notification::make()->title($title)->success()->send();
            } 
            
            // C. TANGANI RESET KE 'diajukan' (Hapus data verifikasi)
            elseif ($newStatus === 'diajukan' && !is_null($record->admin_id)) {
                $record->forceFill([
                    'admin_id' => null,
                    'tanggal_approval' => null,
                ])->saveQuietly();
                Notification::make()->title('Status Diubah ke Diajukan')->warning()->send();
            }
        }
    }
}
