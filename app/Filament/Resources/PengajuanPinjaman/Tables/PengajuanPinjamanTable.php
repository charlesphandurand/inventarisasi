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
        $isAdmin = Auth::user()->hasRole('admin');
        $currentUserId = Auth::id();

        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Nama Peminjam')
                    ->searchable()
                    ->sortable()
                    ->visible(fn () => $isAdmin),
                TextColumn::make('aset.nama_barang')
                    ->label('Nama Barang')
                    ->sortable()
                    ->searchable()
                    ->visible(fn () => $isAdmin),
                
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
                            // Ambil instance Aset secara eksplisit dan lock
                            $aset = Aset::find($record->aset_id);
                            
                            if (!$aset) {
                                Notification::make()->title('Error')->body('Aset tidak ditemukan.')->danger()->send();
                                return; // Rollback otomatis oleh transaction
                            }
                            
                            // Lock baris aset untuk mencegah race condition (pembacaan ganda)
                            $aset->lockForUpdate(); 
                            
                            $jumlahPinjam = $record->jumlah_pinjam;
                            $stokSebelum = $aset->jumlah_barang;

                            if ($stokSebelum < $jumlahPinjam) {
                                Notification::make()
                                    ->title('Gagal Disetujui')
                                    ->body("Jumlah barang '{$aset->nama_barang}' tidak mencukupi untuk pengajuan ini. Sisa stok: {$stokSebelum}")
                                    ->danger()
                                    ->send();
                                return; // Rollback otomatis oleh transaction
                            }
                            
                            $lokasiSebelum = $aset->lokasi;
                            $peminjamNama = $record->user?->name ?? 'Peminjam';
                            
                            $stokSesudah = $stokSebelum - $jumlahPinjam;

                            // 1. RAW UPDATE STOK & LOKASI
                            // Menggunakan WHERE untuk memastikan stok belum berubah (Optimistic Locking)
                            $updated = DB::table('asets')
                                ->where('id', $aset->id)
                                ->where('jumlah_barang', $stokSebelum) 
                                ->update([
                                    'jumlah_barang' => $stokSesudah,
                                    'lokasi' => $peminjamNama,
                                ]);

                            if (!$updated) {
                                // Jika update gagal, berarti ada race condition
                                Notification::make()
                                    ->title('Gagal Disetujui')
                                    ->body('Terjadi masalah konsistensi data (Race Condition). Silakan coba lagi.')
                                    ->danger()
                                    ->send();
                                return; // Rollback otomatis oleh transaction
                            }

                            // 2. Update record pengajuan (menggunakan saveQuietly untuk MENGHINDARI MODEL EVENT 'updated')
                            $record->forceFill([
                                'status' => 'disetujui',
                                'tanggal_approval' => Carbon::now()->setTimezone(config('app.timezone')),
                                'admin_id' => Auth::id(),
                                'lokasi_sebelum' => $lokasiSebelum, // Simpan lokasi awal sebelum dipinjam
                            ])->saveQuietly();
                            
                            // 3. Catat riwayat PINJAM DISETUJUI
                            RiwayatAset::create([
                                'aset_id' => $aset->id,
                                'user_id' => Auth::id(), // Admin yang menyetujui
                                'tipe' => 'pinjam_disetujui',
                                'jumlah_perubahan' => -$jumlahPinjam,
                                'stok_sebelum' => $stokSebelum,
                                'stok_sesudah' => $stokSesudah,
                                'lokasi_sebelum' => $lokasiSebelum,
                                'lokasi_sesudah' => $peminjamNama,
                                'keterangan' => 'Disetujui dan dipinjam oleh ' . $peminjamNama,
                            ]);
                            
                            Notification::make()
                                ->title('Pengajuan Disetujui')
                                ->body("Pengajuan pinjaman {$aset->nama_barang} telah disetujui. Stok tersisa: {$stokSesudah}")
                                ->success()
                                ->send();
                        });
                    }),
                
                // Aksi 'Tolak' (Tidak ada perubahan signifikan)
                Action::make('tolak')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'diajukan' && $isAdmin)
                    ->action(function ($record) {
                        // Tidak perlu transaksi DB karena tidak memengaruhi stok aset
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
                    ->modalDescription('Apakah Anda yakin ingin mengembalikan barang ini? Stok akan dikembalikan ke lokasi semula.')
                    ->action(function ($record) {
                        
                        DB::transaction(function () use ($record) {
                            // Ambil instance Aset secara eksplisit dan lock
                            $aset = Aset::find($record->aset_id);

                            if (!$aset) {
                                Notification::make()->title('Error')->body('Aset tidak ditemukan.')->danger()->send();
                                return; // Rollback otomatis oleh transaction
                            }
                            $aset->lockForUpdate();

                            $jumlahPinjam = $record->jumlah_pinjam;
                            
                            $lokasiSebelum = $record->lokasi_sebelum ?? $aset->lokasi; // Gunakan lokasi_sebelum yang tersimpan
                            $peminjamNama = $record->user?->name ?? 'Peminjam';
                            
                            $stokSebelum = $aset->jumlah_barang;
                            $stokSesudah = $stokSebelum + $jumlahPinjam;
                            
                            // 1. RAW UPDATE STOK & LOKASI
                            // Menggunakan WHERE untuk memastikan stok belum berubah (Optimistic Locking)
                            $updated = DB::table('asets')
                                ->where('id', $aset->id)
                                ->where('jumlah_barang', $stokSebelum) 
                                ->update([
                                    'jumlah_barang' => $stokSesudah,
                                    'lokasi' => $lokasiSebelum, // Kembalikan ke lokasi sebelum dipinjam
                                ]);

                            if (!$updated) {
                                Notification::make()
                                    ->title('Gagal Pengembalian')
                                    ->body('Terjadi masalah konsistensi data (Race Condition). Stok tidak dapat dikembalikan.')
                                    ->danger()
                                    ->send();
                                return; // Rollback otomatis oleh transaction
                            }
                            
                            // 2. Update record pengajuan (menggunakan saveQuietly untuk MENGHINDARI MODEL EVENT 'updated')
                            $record->forceFill([
                                'status' => 'dikembalikan',
                                'tanggal_approval' => Carbon::now()->setTimezone(config('app.timezone')),
                            ])->saveQuietly();

                            // 3. Catat riwayat PINJAM DIKEMBALIKAN
                            RiwayatAset::create([
                                'aset_id' => $aset->id,
                                'user_id' => Auth::id(), // Admin yang mengembalikan
                                'tipe' => 'pinjam_dikembalikan',
                                'jumlah_perubahan' => $jumlahPinjam,
                                'stok_sebelum' => $stokSebelum,
                                'stok_sesudah' => $stokSesudah,
                                'lokasi_sebelum' => $peminjamNama,
                                'lokasi_sesudah' => $lokasiSebelum,
                                'keterangan' => "Barang dikembalikan oleh {$peminjamNama} ke {$lokasiSebelum}",
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
