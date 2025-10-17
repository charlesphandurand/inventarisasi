<?php

namespace App\Filament\Resources\PermintaanBarang\Tables;

use App\Models\Aset;
use App\Models\RiwayatAset;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder; 
use App\Filament\Resources\PermintaanBarang\PermintaanBarangResource;

class PermintaanBarangTable
{
    public static function configure(Table $table): Table
    {
        $isApprover = Auth::user()->hasRole('approver');
        $isMaker = Auth::user()->hasRole('maker');
        $isAdmin = $isMaker || $isApprover; 
        $currentUserId = Auth::id();

        return $table
            ->modifyQueryUsing(function (Builder $query) {
                // Memastikan aset terkait memiliki is_atk = 1 (ATK)
                $query->whereHas('aset', function (Builder $subQuery) {
                    $subQuery->where('is_atk', 1);
                });
            })
            // Tambahkan recordUrl untuk mengontrol klik pada baris
            ->recordUrl(function ($record) use ($isAdmin, $currentUserId, $isMaker, $isApprover) {
                $status = $record->status;
                $isOwner = $record->user_id === $currentUserId;
                
                // Maker: boleh edit status 'diajukan' dan 'diverifikasi',
                // serta 'ditolak' jika penolaknya adalah maker sendiri
                if ($isMaker) {
                    if (in_array($status, ['diajukan', 'diverifikasi'])) {
                        return PermintaanBarangResource::getUrl('edit', ['record' => $record]);
                    }
                    if ($status === 'ditolak' && $record->admin_id === $currentUserId) {
                        return PermintaanBarangResource::getUrl('edit', ['record' => $record]);
                    }
                }

                // Approver:
                // - boleh edit saat 'diverifikasi'
                // - boleh edit 'ditolak' hanya jika admin_id == dirinya
                // - boleh edit MILIKNYA saat status 'diajukan'
                if ($isApprover) {
                    if ($status === 'diverifikasi') {
                        return PermintaanBarangResource::getUrl('edit', ['record' => $record]);
                    }
                    if ($status === 'ditolak' && $record->admin_id === $currentUserId) {
                        return PermintaanBarangResource::getUrl('edit', ['record' => $record]);
                    }
                    if ($status === 'diajukan' && $record->user_id === $currentUserId) {
                        return PermintaanBarangResource::getUrl('edit', ['record' => $record]);
                    }
                    return null;
                }

                // Pemohon hanya boleh mengakses edit jika status 'diajukan'
                if ($isOwner && $status === 'diajukan') {
                    return PermintaanBarangResource::getUrl('edit', ['record' => $record]);
                }

                return null;
            })
            ->columns([
                TextColumn::make('user.name')
                    ->label('Nama Pemohon')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('aset.nama_barang')
                    ->label('Nama Barang (ATK)')
                    ->sortable()
                    ->searchable(),
                
                TextColumn::make('jumlah_pinjam')
                    ->label('Jumlah Permintaan')
                    ->numeric()
                    ->sortable(),
                
                TextColumn::make('jumlah_dikembalikan')
                    ->label('Jml Dikeluarkan')
                    ->numeric()
                    ->sortable()
                    ->placeholder('0'), 

                TextColumn::make('aset.jumlah_barang')
                    ->label('Sisa Barang (Stok)')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('admin.name')
                    ->label('Diverifikasi Oleh')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'diajukan' => 'warning',
                        'diverifikasi' => 'info',
                        'ditolak' => 'danger',
                        'dikeluarkan' => 'success', 
                        default => 'gray',
                    })
                    ->sortable(),
                    
                TextColumn::make('created_at')
                    ->label('Tanggal Pengajuan')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('tanggal_approval')
                    ->label('Tanggal Diverifikasi')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('tanggal_approval', 'desc')
            ->filters([
                // Filter ini disederhanakan karena recordUrl sudah mengatur filtering untuk non-admin
                Filter::make('my_pengajuan')
                    ->label('Permintaan Saya')
                    ->query(fn (Builder $query) => $query->where('user_id', $currentUserId))
                    ->visible(fn () => !$isAdmin), 
            ])
            ->actions([
                // Aksi BARU: Verifikasi & Teruskan ke Approver (Hanya untuk Maker, Status 'diajukan')
                Action::make('verifikasi')
                    ->label('Verifikasi & Teruskan ke Approver')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('info')
                    ->visible(fn ($record) => $record->status === 'diajukan' && $isMaker)
                    ->requiresConfirmation()
                    ->modalHeading('Verifikasi Permintaan')
                    ->modalDescription('Pastikan data dan stok sudah di-review. Status akan diubah menjadi "Diverifikasi" dan diteruskan ke Approver.')
                    ->modalSubmitActionLabel('Ya, Verifikasi & Teruskan')
                    ->action(function ($record) {
                        // Tidak perlu DB::transaction karena tidak ada perubahan stok
                        $record->forceFill([
                            'status' => 'diverifikasi', 
                            'tanggal_approval' => Carbon::now()->setTimezone(config('app.timezone')),
                            'admin_id' => Auth::id(), 
                        ])->saveQuietly();

                        Notification::make()
                            ->title('Permintaan ATK Diverifikasi')
                            ->body("Permintaan {$record->aset->nama_barang} telah diverifikasi dan diteruskan ke Approver.")
                            ->success()
                            ->send();
                    }),

                // Aksi 'Setujui & Keluarkan Barang' (Hanya untuk Approver, Status 'diverifikasi')
                Action::make('setujui')
                    ->label('Setujui & Keluarkan Barang')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'diverifikasi' && $isApprover)
                    ->action(function ($record) {
                        
                        try {
                            DB::transaction(function () use ($record) {
                                $aset = Aset::find($record->aset_id);
                                
                                if (!$aset) {
                                    throw new \Exception('Aset (ATK) tidak ditemukan.');
                                }
                                
                                // Lock the Aset record for update (Penting untuk mencegah race condition)
                                $aset->lockForUpdate();
                                
                                $jumlahPinjam = $record->jumlah_pinjam;
                                $stokSebelum = $aset->jumlah_barang;

                                if ($stokSebelum < $jumlahPinjam) {
                                    throw new \Exception("Jumlah barang '{$aset->nama_barang}' tidak mencukupi. Sisa stok: {$stokSebelum}");
                                }
                                
                                $lokasiGudang = $aset->lokasi; 
                                $peminjamNama = $record->user?->name ?? 'Pemohon';
                                $stokSesudah = $stokSebelum - $jumlahPinjam;

                                // 1. Update STOK menggunakan Query Builder (Optimistic Locking)
                                $updated = DB::table('asets')
                                    ->where('id', $aset->id)
                                    ->where('jumlah_barang', $stokSebelum) 
                                    ->update(['jumlah_barang' => $stokSesudah]);

                                if (!$updated) {
                                    throw new \Exception('Terjadi masalah konsistensi data stok (Race Condition). Silakan coba lagi.');
                                }
                                
                                // 2. Update record pengajuan
                                $record->forceFill([
                                    'status' => 'dikeluarkan',
                                    'tanggal_approval' => Carbon::now()->setTimezone(config('app.timezone')),
                                    'admin_id' => Auth::id(),
                                    'jumlah_dikembalikan' => $jumlahPinjam,
                                    'lokasi_sebelum' => $lokasiGudang, 
                                    ])->saveQuietly();
                                
                                // 3. Catat riwayat PENGELUARAN BARANG ATK
                                RiwayatAset::create([
                                    'aset_id' => $aset->id,
                                    'user_id' => Auth::id(),
                                    'tipe' => 'permintaan_atk_dikeluarkan', 
                                    'jumlah_perubahan' => -$jumlahPinjam,
                                    'stok_sebelum' => $stokSebelum,
                                    'stok_sesudah' => $stokSesudah,
                                    'lokasi_sebelum' => $lokasiGudang, 
                                    'lokasi_sesudah' => $peminjamNama . ' (Diterima)', 
                                    'keterangan' => 'Permintaan ATK disetujui dan dikeluarkan untuk ' . $peminjamNama . ' oleh Approver melalui Action.',
                                ]);
                                
                                Notification::make()
                                    ->title('Permintaan ATK dikeluarkan')
                                    ->body("Permintaan {$aset->nama_barang} telah disetujui dan barang dikeluarkan. Stok tersisa: {$stokSesudah}")
                                    ->success()
                                    ->send();

                            });
                        } catch (\Throwable $e) {
                             // Tampilkan error dari exception yang dilempar di transaksi
                             Notification::make()->title('Gagal Pengeluaran')->body($e->getMessage())->danger()->send();
                        }
                    }),
                
                // Aksi 'Tolak' (Bisa oleh Maker atau Approver)
                Action::make('tolak')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => 
                        // Maker tolak status 'diajukan'
                        ($record->status === 'diajukan' && $isMaker) || 
                        // Approver tolak status 'diverifikasi'
                        ($record->status === 'diverifikasi' && $isApprover)
                        // Aksi tolak tidak diizinkan pada status 'dikeluarkan' di Action
                    )
                    ->action(function ($record) {
                        
                        DB::transaction(function () use ($record) {
                            $record->forceFill([
                                'status' => 'ditolak',
                                'tanggal_approval' => Carbon::now()->setTimezone(config('app.timezone')),
                                'admin_id' => Auth::id(),
                            ])->saveQuietly();

                            Notification::make()
                                ->title('Permintaan Ditolak')
                                ->body('Permintaan berhasil ditolak.')
                                ->danger()
                                ->send();
                        });
                    }),

                // Edit Action: [LOGIKA FINAL KONFIRMASI]
                EditAction::make()
                    ->visible(function ($record) use ($isMaker, $isApprover, $currentUserId) {
                        $status = $record->status;
                        $isOwner = $record->user_id === $currentUserId;

                        if ($isMaker) {
                            return in_array($status, ['diajukan', 'diverifikasi']) || ($status === 'ditolak' && $record->admin_id === $currentUserId);
                        }
                        if ($isApprover) {
                            if ($status === 'diverifikasi') return true;
                            if ($status === 'ditolak') return $record->admin_id === $currentUserId;
                            if ($status === 'diajukan') return $isOwner;
                            return false;
                        }
                        return $isOwner && $status === 'diajukan';
                    }),
                
                // Delete Action: [LOGIKA FINAL KONFIRMASI]
                DeleteAction::make()
                    ->visible(function ($record) use ($currentUserId) {
                        // Hanya pemilik dan hanya saat status diajukan
                        return $record->user_id === $currentUserId && $record->status === 'diajukan';
                    })
                    ->action(function ($record) {
                        // Pastikan hanya pemilik dan status diajukan (double-check server side)
                        if ($record->user_id !== Auth::id() || $record->status !== 'diajukan') {
                            Notification::make()->title('Aksi ditolak').body('Anda tidak dapat menghapus permintaan ini.').danger()->send();
                            return;
                        }
                        $record->delete();
                        Notification::make()->title('Permintaan dihapus').success()->send();
                    }),
            ])
            ->bulkActions([
                // BulkActionGroup::make([
                //     DeleteBulkAction::make()
                //         ->visible(fn () => $isAdmin),
                // ]),
            ]);
    }
}