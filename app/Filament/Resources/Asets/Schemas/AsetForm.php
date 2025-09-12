<?php

namespace App\Filament\Resources\Asets\Schemas;

use Filament\Forms\Components\Textarea; // Pastikan Anda mengimpor ini
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use App\Models\Aset;
use Filament\Schemas\Schema;

class AsetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama_barang')
                    ->required(),
                Select::make('lokasi')
                    ->label('Lokasi')
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->options(function ($get) {
                        $options = Aset::query()
                            ->whereNotNull('lokasi')
                            ->distinct()
                            ->pluck('lokasi', 'lokasi')
                            ->toArray();
                        $current = $get('lokasi');
                        if ($current && ! array_key_exists($current, $options)) {
                            $options[$current] = $current;
                        }
                        return $options;
                    })
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Lokasi Baru')
                            ->required(),
                    ])
                    ->createOptionUsing(fn (array $data) => $data['name'])
                    ->required(),
                // atas_nama dihapus
                TextInput::make('jumlah_barang')
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
