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
use App\Models\RiwayatAset;
use Filament\Forms\Components\TextInput; 
use Illuminate\Database\Eloquent\Builder; // Import untuk Builder

class PengajuanPinjamanTable
{
    public static function configure(Table $table): Table
    {
        // Ganti isAdmin menjadi hasAdminRole untuk mencakup Maker dan Approver
        $hasAdminRole = Auth::user()->hasAnyRole(['maker', 'approver']); 
        $isMaker = Auth::user()->hasRole('maker');
        $isApprover = Auth::user()->hasRole('approver');
        $currentUserId = Auth::id();

        return $table
            // PERUBAHAN KRITIS: Memodifikasi query utama untuk hanya menampilkan
            // pengajuan yang terkait dengan aset yang BUKAN ATK (is_atk != 1).
            ->modifyQueryUsing(function (Builder $query) {
                // Memastikan aset terkait memiliki is_atk != 1 (BUKAN ATK)
                $query->whereHas('aset', function (Builder $subQuery) {
                    $subQuery->where('is_atk', '!=', 1); // Diperbarui sesuai saran Anda
                });
            })
            // Selaraskan hak akses klik baris ke Edit seperti PermintaanBarang
            ->recordUrl(function ($record) use ($isMaker, $isApprover, $currentUserId) {
                $status = $record->status;
                $isOwner = $record->user_id === $currentUserId;

                if ($isMaker) {
                    if ($status === 'diajukan') return \App\Filament\Resources\PengajuanPinjaman\PengajuanPinjamanResource::getUrl('edit', ['record' => $record]);
                    if ($status === 'ditolak' && $record->admin_id === $currentUserId) return \App\Filament\Resources\PengajuanPinjaman\PengajuanPinjamanResource::getUrl('edit', ['record' => $record]);
                }

                if ($isApprover) {
                    if ($status === 'diajukan' && $isOwner) return \App\Filament\Resources\PengajuanPinjaman\PengajuanPinjamanResource::getUrl('edit', ['record' => $record]);
                    if ($status === 'ditolak' && $record->admin_id === $currentUserId) return \App\Filament\Resources\PengajuanPinjaman\PengajuanPinjamanResource::getUrl('edit', ['record' => $record]);
                }

                if ($isOwner && $status === 'diajukan') return \App\Filament\Resources\PengajuanPinjaman\PengajuanPinjamanResource::getUrl('edit', ['record' => $record]);

                return null;
            })
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
                
                // KOLOM BARU: Jumlah yang Dikembalikan
                TextColumn::make('jumlah_dikembalikan')
                    ->label('Jml Dikembalikan')
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
            ->defaultSort('tanggal_approval', 'desc')
            ->filters([
                Filter::make('my_pengajuan')
                    ->label('Pengajuan Saya')
                    // Filter hanya tampil untuk non-Admin
                    ->query(fn ($query) => $hasAdminRole ? $query : $query->where('user_id', $currentUserId)) 
                    ->visible(fn () => !$hasAdminRole), 
            ])
            ->actions([
                // Aksi 'Verifikasi' (Maker, dari diajukan → diverifikasi)
                Action::make('verifikasi')
                    ->label('Verifikasi & Teruskan')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('info')
                    ->visible(fn ($record) => $record->status === 'diajukan' && $isMaker)
                    ->action(function ($record) {
                        $record->forceFill([
                            'status' => 'diverifikasi',
                            'tanggal_approval' => Carbon::now()->setTimezone(config('app.timezone')),
                            'admin_id' => Auth::id(),
                        ])->saveQuietly();

                        Notification::make()
                            ->title('Pengajuan Diverifikasi')
                            ->body('Pengajuan telah diverifikasi dan diteruskan ke Approver.')
                            ->success()
                            ->send();
                    }),

                // Aksi 'Setujui' 
                Action::make('setujui')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    // Hanya Approver yang bisa melihat dan status harus 'diverifikasi'
                    ->visible(fn ($record) => $record->status === 'diverifikasi' && $isApprover) 
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
                            
                            $lokasiGudang = $aset->lokasi; 
                            $peminjamNama = $record->user?->name ?? 'Peminjam';
                            
                            $stokSesudah = $stokSebelum - $jumlahPinjam;

                            // 1. RAW UPDATE STOK
                            $updated = DB::table('asets')
                                ->where('id', $aset->id)
                                ->where('jumlah_barang', $stokSebelum) 
                                ->update([
                                    'jumlah_barang' => $stokSesudah,
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
                                'lokasi_sebelum' => $lokasiGudang, 
                            ])->saveQuietly();
                            
                            // 3. Catat riwayat PINJAM DISETUJUI
                            RiwayatAset::create([
                                'aset_id' => $aset->id,
                                'user_id' => Auth::id(),
                                'tipe' => 'pinjam_disetujui',
                                'jumlah_perubahan' => -$jumlahPinjam,
                                'stok_sebelum' => $stokSebelum,
                                'stok_sesudah' => $stokSesudah,
                                'lokasi_sebelum' => $lokasiGudang, 
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
                
                // Aksi 'Tolak' 
                Action::make('tolak')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    // Maker menolak saat 'diajukan', Approver menolak saat 'diverifikasi'
                    ->visible(fn ($record) => ($record->status === 'diajukan' && $isMaker) || ($record->status === 'diverifikasi' && $isApprover)) 
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

                // AKSI 'DIKEMBALIKAN' (khusus Approver saja)
                Action::make('dikembalikan')
                    ->label('Dikembalikan')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('info')
                    // Hanya Approver yang bisa melihat dan status harus 'disetujui'
                    ->visible(fn ($record) => $record->status === 'disetujui' && $isApprover) 
                    ->modalHeading('Pengembalian Aset')
                    ->modalDescription('Masukkan jumlah unit yang dikembalikan oleh peminjam.')
                    ->fillForm(function ($record) {
                        // Hitung sisa yang belum dikembalikan
                        $sisaBelumDikembalikan = $record->jumlah_pinjam - ($record->jumlah_dikembalikan ?? 0);
                        return ['jumlah_dikembalikan_saat_ini' => $sisaBelumDikembalikan]; 
                    })
                    ->form([
                        // Field: Jumlah yang Dikembalikan
                        TextInput::make('jumlah_dikembalikan_saat_ini')
                            ->label('Jumlah yang Dikembalikan Sekarang')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            // Batasi maksimal sejumlah sisa pinjaman yang belum dikembalikan
                            ->maxValue(fn ($record) => $record->jumlah_pinjam - ($record->jumlah_dikembalikan ?? 0)) 
                            ->hint(fn ($record) => "Maksimal sisa pinjaman: " . ($record->jumlah_pinjam - ($record->jumlah_dikembalikan ?? 0)) . " unit"),
                    ])
                    ->action(function (array $data, $record) { 
                        
                        $jumlahDikembalikan = (int) $data['jumlah_dikembalikan_saat_ini'];
                        
                        DB::transaction(function () use ($record, $jumlahDikembalikan) {
                            $aset = Aset::find($record->aset_id);

                            if (!$aset) {
                                Notification::make()->title('Error')->body('Aset tidak ditemukan.')->danger()->send();
                                return;
                            }
                            $aset->lockForUpdate();

                            $lokasiGudang = $record->lokasi_sebelum ?? $aset->lokasi; 
                            $peminjamNama = $record->user?->name ?? 'Peminjam';
                            
                            $stokSebelum = $aset->jumlah_barang;
                            $stokSesudah = $stokSebelum + $jumlahDikembalikan; // Tambah stok
                            
                            // Hitung sisa pinjaman setelah pengembalian ini
                            $sisaPinjam = $record->jumlah_pinjam - ($record->jumlah_dikembalikan ?? 0) - $jumlahDikembalikan;

                            // 1. RAW UPDATE STOK
                            $updated = DB::table('asets')
                                ->where('id', $aset->id)
                                ->where('jumlah_barang', $stokSebelum) 
                                ->update([
                                    'jumlah_barang' => $stokSesudah,
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
                                // Jika sisa pinjam 0, status menjadi 'dikembalikan'. Jika > 0, status tetap 'disetujui' (Partial Return)
                                'status' => $sisaPinjam <= 0 ? 'dikembalikan' : 'disetujui',
                                'tanggal_approval' => Carbon::now()->setTimezone(config('app.timezone')),
                                'jumlah_dikembalikan' => ($record->jumlah_dikembalikan ?? 0) + $jumlahDikembalikan, // Kumulatif
                            ])->saveQuietly();

                            // 3. Catat riwayat PINJAM DIKEMBALIKAN 
                            RiwayatAset::create([
                                'aset_id' => $aset->id,
                                'user_id' => Auth::id(),
                                'tipe' => 'pinjam_dikembalikan',
                                'jumlah_perubahan' => $jumlahDikembalikan, 
                                'stok_sebelum' => $stokSebelum,
                                'stok_sesudah' => $stokSesudah,
                                'lokasi_sebelum' => $peminjamNama, 
                                'lokasi_sesudah' => $lokasiGudang, 
                                'keterangan' => "Pengembalian parsial aset oleh {$peminjamNama}. Jumlah: {$jumlahDikembalikan} unit.",
                            ]);

                            Notification::make()
                                ->title('Barang Dikembalikan')
                                ->body("Barang {$aset->nama_barang} sebanyak {$jumlahDikembalikan} unit telah dikembalikan. Sisa pinjaman: " . max(0, $sisaPinjam) . " unit.")
                                ->success()
                                ->send();
                        });
                    }),

                // Aksi Edit (samakan dengan recordUrl rules)
                EditAction::make()
                    ->visible(function ($record) use ($isMaker, $isApprover, $currentUserId) {
                        $status = $record->status;
                        $isOwner = $record->user_id === $currentUserId;

                        if ($isMaker) {
                            return $status === 'diajukan' || ($status === 'ditolak' && $record->admin_id === $currentUserId);
                        }
                        if ($isApprover) {
                            if ($status === 'diajukan' && $isOwner) return true;
                            if ($status === 'ditolak' && $record->admin_id === $currentUserId) return true;
                            return false;
                        }
                        return $isOwner && $status === 'diajukan';
                    }),
                
                // Aksi Delete: hanya pemilik saat status diajukan
                DeleteAction::make()
                    ->visible(fn ($record) => $record->user_id === $currentUserId && $record->status === 'diajukan')
                    ->action(function ($record) {
                        if ($record->user_id !== Auth::id() || $record->status !== 'diajukan') {
                            Notification::make()->title('Aksi ditolak').body('Anda tidak dapat menghapus pengajuan ini.').danger()->send();
                            return;
                        }
                        $record->delete();
                        Notification::make()->title('Pengajuan dihapus').success()->send();
                    }),
            ])
            // Nonaktifkan bulk delete
            ->bulkActions([
            ]);
    }
}