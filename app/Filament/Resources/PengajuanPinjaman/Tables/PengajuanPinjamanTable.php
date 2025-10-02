<?php

namespace App\Filament\Resources\PengajuanPinjaman\Tables;

use App\Models\Aset;
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
use App\Models\RiwayatAset; // Tambahkan import untuk RiwayatAset

class PengajuanPinjamanTable
{
    public static function configure(Table $table): Table
    {
        $isAdmin = Auth::user()->hasAnyRole(['approver']);
        $currentUserId = Auth::id();

        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Nama Peminjam')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('aset.nama_barang')
                    ->label('Nama Barang')
                    ->sortable()
                    ->searchable(),
                
                TextColumn::make('jumlah_pinjam')
                    ->label('Jumlah Pinjam')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('aset.jumlah_barang')
                    ->label('Sisa Barang')
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
                        'disetujui' => 'success',
                        'ditolak' => 'danger',
                        'dikembalikan' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                    
                TextColumn::make('created_at')
                    ->label('Tanggal Pengajuan')
                    ->dateTime()
                    ->sortable()
                    ->default(function () {
                        return Carbon::now()->setTimezone(config('app.timezone'))->toDateTimeString();
                    }),

                TextColumn::make('tanggal_approval')
                    ->label('Tanggal Diverifikasi')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('my_pengajuan')
                    ->label('Pengajuan Saya')
                    ->query(fn ($query) => $isAdmin ? $query : $query->where('user_id', $currentUserId))
                    ->visible(fn () => !$isAdmin), 
            ])
            ->actions([
                // Aksi 'Setujui'
                Action::make('setujui')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'diajukan' && $isAdmin)
                    ->action(function ($record) {
                        
                        DB::transaction(function () use ($record) {
                            $aset = Aset::find($record->aset_id);
                            
                            if (!$aset) {
                                Notification::make()->title('Error')->body('Aset tidak ditemukan.')->danger()->send();
                                return;
                            }
                            
                            $aset->lockForUpdate(); 
                            
                            $jumlahPinjam = $record->jumlah_pinjam;
                            $stokSebelum = $aset->jumlah_barang;

                            if ($stokSebelum < $jumlahPinjam) {
                                Notification::make()
                                    ->title('Gagal Disetujui')
                                    ->body("Jumlah barang '{$aset->nama_barang}' tidak mencukupi. Sisa stok: {$stokSebelum}")
                                    ->danger()
                                    ->send();
                                return;
                            }
                            
                            $lokasiGudang = $aset->lokasi; // Lokasi Gudang (TETAP)
                            $peminjamNama = $record->user?->name ?? 'Peminjam';
                            
                            $stokSesudah = $stokSebelum - $jumlahPinjam;

                            // 1. RAW UPDATE STOK SAJA (HAPUS PERUBAHAN LOKASI DARI ASSET)
                            $updated = DB::table('asets')
                                ->where('id', $aset->id)
                                ->where('jumlah_barang', $stokSebelum) 
                                ->update([
                                    'jumlah_barang' => $stokSesudah,
                                    // ❌ BARIS 'lokasi' => $peminjamNama, DIHAPUS ❌
                                ]);

                            if (!$updated) {
                                Notification::make()
                                    ->title('Gagal Disetujui')
                                    ->body('Terjadi masalah konsistensi data (Race Condition). Silakan coba lagi.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            // 2. Update record pengajuan
                            $record->forceFill([
                                'status' => 'disetujui',
                                'tanggal_approval' => Carbon::now()->setTimezone(config('app.timezone')),
                                'admin_id' => Auth::id(),
                                'lokasi_sebelum' => $lokasiGudang, // Simpan lokasi awal sebelum dipinjam
                            ])->saveQuietly();
                            
                            // 3. Catat riwayat PINJAM DISETUJUI (LOKASI SESUDAH = NAMA PEMINJAM)
                            RiwayatAset::create([
                                'aset_id' => $aset->id,
                                'user_id' => Auth::id(),
                                'tipe' => 'pinjam_disetujui',
                                'jumlah_perubahan' => -$jumlahPinjam,
                                'stok_sebelum' => $stokSebelum,
                                'stok_sesudah' => $stokSesudah,
                                'lokasi_sebelum' => $lokasiGudang, // Lokasi Gudang
                                'lokasi_sesudah' => $peminjamNama, // 👈 INI YANG BENAR: LOKASI RIWAYAT = PEMINJAM
                                'keterangan' => 'Disetujui dan dipinjam oleh ' . $peminjamNama,
                            ]);
                            
                            Notification::make()
                                ->title('Pengajuan Disetujui')
                                ->body("Pengajuan pinjaman {$aset->nama_barang} telah disetujui. Stok tersisa: {$stokSesudah}")
                                ->success()
                                ->send();
                        });
                    }),
                
                // Aksi 'Tolak' (Tidak ada perubahan stok/lokasi)
                Action::make('tolak')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'diajukan' && $isAdmin)
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'ditolak',
                            'tanggal_approval' => Carbon::now()->setTimezone(config('app.timezone')),
                            'admin_id' => Auth::id(),
                        ]);

                        Notification::make()
                            ->title('Pengajuan Ditolak')
                            ->body('Pengajuan berhasil ditolak.')
                            ->danger()
                            ->send();
                    }),

                // Aksi 'Dikembalikan'
                Action::make('dikembalikan')
                    ->label('Dikembalikan')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('info')
                    ->visible(fn ($record) => $record->status === 'disetujui' && $isAdmin)
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Pengembalian')
                    ->modalDescription('Apakah Anda yakin ingin mengembalikan barang ini? Stok akan dikembalikan.')
                    ->action(function ($record) {
                        
                        DB::transaction(function () use ($record) {
                            $aset = Aset::find($record->aset_id);

                            if (!$aset) {
                                Notification::make()->title('Error')->body('Aset tidak ditemukan.')->danger()->send();
                                return;
                            }
                            $aset->lockForUpdate();

                            $jumlahPinjam = $record->jumlah_pinjam;
                            
                            // Ambil lokasi Gudang dari record Peminjaman
                            $lokasiGudang = $record->lokasi_sebelum ?? $aset->lokasi; 
                            $peminjamNama = $record->user?->name ?? 'Peminjam';
                            
                            $stokSebelum = $aset->jumlah_barang;
                            $stokSesudah = $stokSebelum + $jumlahPinjam;
                            
                            // 1. RAW UPDATE STOK SAJA (HAPUS PERUBAHAN LOKASI DARI ASSET)
                            $updated = DB::table('asets')
                                ->where('id', $aset->id)
                                ->where('jumlah_barang', $stokSebelum) 
                                ->update([
                                    'jumlah_barang' => $stokSesudah,
                                    // ❌ BARIS 'lokasi' => $lokasiSebelum, DIHAPUS ❌ (Lokasi di aset utama sudah Gudang)
                                ]);

                            if (!$updated) {
                                Notification::make()
                                    ->title('Gagal Pengembalian')
                                    ->body('Terjadi masalah konsistensi data (Race Condition). Stok tidak dapat dikembalikan.')
                                    ->danger()
                                    ->send();
                                return;
                            }
                            
                            // 2. Update record pengajuan
                            $record->forceFill([
                                'status' => 'dikembalikan',
                                'tanggal_approval' => Carbon::now()->setTimezone(config('app.timezone')),
                            ])->saveQuietly();

                            // 3. Catat riwayat PINJAM DIKEMBALIKAN (LOKASI SESUDAH = LOKASI GUDANG)
                            RiwayatAset::create([
                                'aset_id' => $aset->id,
                                'user_id' => Auth::id(),
                                'tipe' => 'pinjam_dikembalikan',
                                'jumlah_perubahan' => $jumlahPinjam,
                                'stok_sebelum' => $stokSebelum,
                                'stok_sesudah' => $stokSesudah,
                                'lokasi_sebelum' => $peminjamNama, // Lokasi Peminjam
                                'lokasi_sesudah' => $lokasiGudang, // 👈 INI YANG BENAR: LOKASI RIWAYAT = GUDANG
                                'keterangan' => "Barang dikembalikan oleh {$peminjamNama} ke {$lokasiGudang}",
                            ]);

                            Notification::make()
                                ->title('Barang Dikembalikan')
                                ->body("Barang {$aset->nama_barang} sebanyak {$jumlahPinjam} unit telah dikembalikan ke stok awal.")
                                ->success()
                                ->send();
                        });
                    }),

                EditAction::make()->visible(fn ($record) => $isAdmin || ($record->status === 'diajukan' && $record->user_id === $currentUserId)),
                
                DeleteAction::make()->visible(fn () => $isAdmin),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => $isAdmin),
                ]),
            ]);
    }
}