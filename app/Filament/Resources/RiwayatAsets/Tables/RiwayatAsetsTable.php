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
                    ->label('Tipe Transaksi')
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
                        // Menambahkan status baru untuk Permintaan ATK
                        'permintaan_atk_dikeluarkan' => 'success', 
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('jumlah_perubahan')
                    ->label('Perubahan Stok')
                    ->numeric(),
                TextColumn::make('stok_sebelum')
                    ->label('Stok Sebelum')
                    ->numeric(),
                TextColumn::make('stok_sesudah')
                    ->label('Stok Sesudah')
                    ->numeric(),
                TextColumn::make('harga_sebelum')
                    ->label('Harga Sebelum')
                    ->money('IDR', locale: 'id')
                    ->toggleable(isToggledHiddenByDefault: true), // Sembunyikan secara default
                TextColumn::make('harga_sesudah')
                    ->label('Harga Sesudah')
                    ->money('IDR', locale: 'id')
                    ->toggleable(isToggledHiddenByDefault: true), // Sembunyikan secara default
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
                // Filter yang disederhanakan: fokus pada perubahan stok/status kunci
                SelectFilter::make('tipe')
                    ->label('Filter Tipe Transaksi')
                    ->options([
                        'create' => 'Aset Baru Dibuat',
                        'penambahan' => 'Penambahan Stok',
                        'pengurangan' => 'Pengurangan Stok Manual',
                        'pinjam_disetujui' => 'Pinjaman Disetujui',
                        'pinjam_dikembalikan' => 'Pinjaman Dikembalikan',
                        'permintaan_atk_dikeluarkan' => 'ATK Dikeluarkan', // Filter ATK
                        'lokasi_update' => 'Update Lokasi',
                        'harga_update' => 'Update Harga',
                    ]),
            ]);
    }
}
