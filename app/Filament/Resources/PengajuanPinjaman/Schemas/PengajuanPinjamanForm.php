<?php

namespace App\Filament\Resources\PengajuanPinjaman\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use App\Models\Aset;

class PengajuanPinjamanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('aset_id')
                    ->label('Nama Barang')
                    ->relationship(name: 'aset', titleAttribute: 'nama_barang')
                    ->searchable()
                    ->preload() // Tambahkan baris ini
                    ->required(),
                
                TextInput::make('jumlah_pinjam')
                    ->required()
                    ->numeric(),
                
                DateTimePicker::make('tanggal_pengajuan')
                    ->required()
                    ->default(now())
                    ->disabled(),
                
                DateTimePicker::make('tanggal_approval')
                    ->disabled()
                    ->visibleOn('edit'),
                
                Select::make('admin_id')
                    ->label('Disetujui Oleh')
                    ->relationship('admin', 'name')
                    ->disabled()
                    ->default(auth()->id()),
                
                Select::make('status')
                    ->options([
                        'diajukan' => 'Diajukan',
                        'disetujui' => 'Disetujui',
                        'ditolak' => 'Ditolak',
                    ])
                    ->default('diajukan')
                    ->required(),
            ]);
    }
}