<?php

namespace App\Filament\Resources\PengajuanPinjaman\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction; // <-- Add this import
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Filament\Notifications\Notification;

class PengajuanPinjamanTable
{
    public static function configure(Table $table): Table
    {
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

                TextColumn::make('tanggal_pengajuan')
                    ->label('Tanggal Pengajuan')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('tanggal_approval')
                    ->label('Tanggal Approval')
                    ->dateTime()
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
                    ->label('Dibuat Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Diperbarui Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Action::make('setujui')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'diajukan')
                    ->action(function ($record) {
                        $aset = $record->aset;
                        $jumlahPinjam = $record->jumlah_pinjam;
                        
                        if ($aset->sisa_barang >= $jumlahPinjam) {
                            $aset->sisa_barang -= $jumlahPinjam;
                            $aset->save();

                            $record->update([
                                'status' => 'disetujui',
                                'tanggal_approval' => Carbon::now(),
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
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}