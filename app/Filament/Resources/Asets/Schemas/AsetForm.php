<?php

namespace App\Filament\Resources\Asets\Schemas;

use Filament\Forms\Components\Textarea; // Pastikan Anda mengimpor ini
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AsetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama_barang')
                    ->required(),
                TextInput::make('lokasi')
                    ->required(),
                TextInput::make('atas_nama')
                    ->required(),
                TextInput::make('jumlah_barang')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('sisa_barang')
                    ->required()
                    ->numeric()
                    ->default(0),
                Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->placeholder('Masukkan detail keterangan aset di sini...')
                    ->cols(4)
                    ->rows(4)
                    ->maxLength(100),
            ]);
    }
}
