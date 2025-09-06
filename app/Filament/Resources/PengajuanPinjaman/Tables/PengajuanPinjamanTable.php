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
                TextColumn::make('aset.nama_barang')
                    ->label('Nama Barang')
                    ->sortable()
                    ->searchable(),
                
                TextColumn::make('jumlah_pinjam')
                    ->label('Jumlah Pinjam')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('admin.name')
                    ->label('Disetujui Oleh')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'diajukan' => 'warning',
                        'disetujui' => 'success',
                        'ditolak' => 'danger',
                    }),
                    
                TextColumn::make('created_at')
                    ->label('Tanggal Pengajuan')
                    ->dateTime()
                    ->timezone('Asia/Makassar')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Tanggal Approval')
                    ->dateTime()
                    ->timezone('Asia/Makassar')
                    ->sortable(),
            ])
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
                        
                        if ($aset->sisa_barang >= $jumlahPinjam) {
                            $aset->sisa_barang -= $jumlahPinjam;
                            $aset->save();

                            $record->update([
                                'status' => 'disetujui',
                                'tanggal_approval' => Carbon::now('Asia/Makassar'),
                                'admin_id' => Auth::id(),
                            ]);

                            Notification::make()
                                ->title('Pengajuan Disetujui')
                                ->body('Sisa barang berhasil diperbarui.')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Gagal Disetujui')
                                ->body('Sisa barang tidak mencukupi untuk pengajuan ini.')
                                ->danger()
                                ->send();
                        }
                    }),

                // Admin bisa edit semua, user hanya bisa edit miliknya sendiri PENTING!!!!
                EditAction::make()->visible(fn ($record) => $isAdmin || $record->user_id === $currentUserId),
                // PENTING!!!!
                
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