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
use Filament\Forms\Components\TextInput; 
use Illuminate\Database\Eloquent\Builder; 

class PermintaanBarangTable
{
    public static function configure(Table $table): Table
    {
        $isAdmin = Auth::user()->hasAnyRole(['approver']);
        $currentUserId = Auth::id();

        return $table
            // PERUBAHAN KRITIS: Memodifikasi query utama untuk hanya menampilkan
            // pengajuan yang terkait dengan aset yang KHUSUS ATK (is_atk = 1).
            ->modifyQueryUsing(function (Builder $query) {
                // Memastikan aset terkait memiliki is_atk = 1 (ATK)
                $query->whereHas('aset', function (Builder $subQuery) {
                    $subQuery->where('is_atk', 1);
                });
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
                
                // Menggunakan kolom 'jumlah_dikembalikan' untuk menyimpan jumlah yang dikeluarkan
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
                        'disetujui' => 'success',
                        'ditolak' => 'danger',
                        // PERUBAHAN STATUS: Ganti 'dikeluarkan' dengan 'dikeluarkan' untuk menghindari error truncation
                        'dikeluarkan' => 'info', 
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
                    ->label('Permintaan Saya')
                    ->query(fn ($query) => $isAdmin ? $query : $query->where('user_id', $currentUserId))
                    ->visible(fn () => !$isAdmin), 
            ])
            ->actions([
                // Aksi 'Setujui' (Mengurangi stok ATK)
                Action::make('setujui')
                    ->label('Setujui & Keluarkan Barang')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'diajukan' && $isAdmin)
                    ->action(function ($record) {
                        
                        DB::transaction(function () use ($record) {
                            $aset = Aset::find($record->aset_id);
                            
                            if (!$aset) {
                                Notification::make()->title('Error')->body('Aset (ATK) tidak ditemukan.')->danger()->send();
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
                            $peminjamNama = $record->user?->name ?? 'Pemohon';
                            
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
                                // PERUBAHAN STATUS: Mengganti menjadi 'dikeluarkan'
                                'status' => 'dikeluarkan', 
                                'tanggal_approval' => Carbon::now()->setTimezone(config('app.timezone')),
                                'admin_id' => Auth::id(),
                                'jumlah_dikembalikan' => $jumlahPinjam, // Jumlah yang dikeluarkan
                                'lokasi_sebelum' => $lokasiGudang, 
                            ])->saveQuietly();
                            
                            // 3. Catat riwayat PENGELUARAN BARANG ATK
                            RiwayatAset::create([
                                'aset_id' => $aset->id,
                                'user_id' => Auth::id(),
                                'tipe' => 'permintaan_atk_dikeluarkan', // Update tipe riwayat
                                'jumlah_perubahan' => -$jumlahPinjam,
                                'stok_sebelum' => $stokSebelum,
                                'stok_sesudah' => $stokSesudah,
                                'lokasi_sebelum' => $lokasiGudang, 
                                'lokasi_sesudah' => $peminjamNama . ' (Diterima)', 
                                'keterangan' => 'Permintaan ATK disetujui dan dikeluarkan untuk ' . $peminjamNama,
                            ]);
                            
                            Notification::make()
                                ->title('Permintaan ATK dikeluarkan')
                                ->body("Permintaan {$aset->nama_barang} telah disetujui dan barang dikeluarkan. Stok tersisa: {$stokSesudah}")
                                ->success()
                                ->send();
                        });
                    }),
                
                // Aksi 'Tolak' (LOGIKA TIDAK BERUBAH)
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
                            ->title('Permintaan Ditolak')
                            ->body('Permintaan berhasil ditolak.')
                            ->danger()
                            ->send();
                    }),

                // AKSI 'DIKEMBALIKAN' DIHILANGKAN (sesuai kebutuhan ATK)
                
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
