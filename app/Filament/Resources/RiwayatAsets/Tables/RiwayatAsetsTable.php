<?php

namespace App\Filament\Resources\RiwayatAsets\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RiwayatAsetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('aset.nama_barang')
                    ->label('Aset')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('aset.nama_vendor')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tipe')
                    ->label('Tipe')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pinjam_dikembalikan' => 'warning',
                        'create' => 'success',
                        'pinjam_disetujui' => 'primary',
                        'lokasi_update' => 'info',
                        'harga_update' => 'info',
                        'pinjam_dihapus' => 'danger',
                        'penambahan' => 'success',
                        'pengurangan' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('jumlah_perubahan')
                    ->label('Stok')
                    ->numeric(),
                TextColumn::make('stok_sebelum')
                    ->label('Stok Sebelum')
                    ->numeric(),
                TextColumn::make('stok_sesudah')
                    ->label('Stok Sesudah')
                    ->numeric(),
                TextColumn::make('harga_sebelum')
                    ->label('Harga Sebelum')
                    ->money('IDR', locale: 'id'),
                TextColumn::make('harga_sesudah')
                    ->label('Harga Sesudah')
                    ->money('IDR', locale: 'id'),
                TextColumn::make('lokasi_sebelum')
                    ->label('Lokasi Sebelumnya'),
                TextColumn::make('lokasi_sesudah')
                    ->label('Lokasi Sesudah'),
                TextColumn::make('user.name')
                    ->label('Diubah Oleh')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('tipe')
                    ->options([
                        'create' => 'Create',
                        'update' => 'Update',
                        'penambahan' => 'Penambahan',
                        'pengurangan' => 'Pengurangan',
                        'harga_update' => 'Update Harga',
                        'lokasi_update' => 'Update Lokasi',
                        'pinjam_disetujui' => 'Pinjam Disetujui',
                        'pinjam_dikembalikan' => 'Pinjam Dikembalikan',
                        'pinjam_dihapus' => 'Pinjam Dihapus',
                    ]),
            ]);
    }
}


