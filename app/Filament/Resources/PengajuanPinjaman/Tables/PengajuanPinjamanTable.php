<?php

namespace App\Filament\Resources\PengajuanPinjaman\Tables;

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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;

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

                // Kolom baru untuk menampilkan sisa barang di tabel
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
                // Filter untuk user biasa agar hanya lihat pengajuan miliknya sendiri
                Filter::make('my_pengajuan')
                    ->label('Pengajuan Saya')
                    ->query(fn ($query) => $isAdmin ? $query : $query->where('user_id', $currentUserId))
                    ->visible(fn () => !$isAdmin), // Hanya muncul untuk user biasa
            ])
            ->actions([
                // Hanya admin yang bisa setujui
                Action::make('setujui')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'diajukan' && $isAdmin)
                    ->action(function ($record) {
                        $aset = $record->aset;
                        $jumlahPinjam = $record->jumlah_pinjam;
                        
                        // VALIDASI PENTING: Cek jumlah_barang
                        if ($aset->jumlah_barang >= $jumlahPinjam) {
                            // Kurangi jumlah_barang dengan jumlah yang disetujui
                            $aset->update([
                                'jumlah_barang' => $aset->jumlah_barang - $jumlahPinjam
                            ]);
                            
                            $record->update([
                                'status' => 'disetujui',
                                'tanggal_approval' => Carbon::now()->setTimezone(config('app.timezone')),
                                'admin_id' => Auth::id(),
                            ]);
                        } else {
                            Notification::make()
                                ->title('Gagal Disetujui')
                                ->body('Jumlah barang tidak mencukupi untuk pengajuan ini.')
                                ->danger()
                                ->send();
                        }
                    }),
                
                // Tambahkan aksi 'Tolak' untuk admin
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

                // Admin bisa edit semua, user hanya bisa edit miliknya sendiri PENTING!!!!
                EditAction::make()->visible(fn ($record) => $isAdmin || $record->user_id === $currentUserId),
                
                // Hanya admin yang bisa delete
                DeleteAction::make()->visible(fn () => $isAdmin),
            ])
            ->bulkActions([
                // Hanya admin yang bisa delete bulk
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => $isAdmin),
                ]),
            ]);
    }
}
