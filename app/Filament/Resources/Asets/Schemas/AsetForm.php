<?php

namespace App\Filament\Resources\Asets\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use App\Models\Aset;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
// Tambahan Baru:
use Filament\Forms\Components\Toggle; 
use Filament\Forms\Components\DatePicker; 
// use LaraZeus\Qr\Components\Qr; // HAPUS: Komponen ini menyebabkan masalah penyimpanan JSON ganda

class AsetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama_barang')
                    ->required(),
                
                // Fitur 1: ATK Switch dan Expired Date
                Toggle::make('is_atk')
                    ->label('Apakah ini Barang ATK?')
                    ->default(false)
                    ->live(),
                
                DatePicker::make('expired_date')
                    ->label('Expired Date')
                    ->placeholder('Pilih tanggal kadaluarsa')
                    // Logic visibilitas ATK tetap sama
                    ->hidden(fn ($get): bool => !$get('is_atk')), 

                // Fitur 2: Kondisi Barang
                Select::make('kondisi_barang')
                    ->label('Kondisi Barang')
                    ->options([
                        'Baik' => 'Baik',
                        'Kurang Baik' => 'Kurang Baik',
                        'Rusak' => 'Rusak',
                    ])
                    ->native(false)
                    ->default('Baik')
                    ->required(),
                
                // --- Kolom yang sudah ada ---
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
                TextInput::make('jumlah_barang')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('nama_vendor')
                    ->label('Nama Vendor')
                    ->nullable(),
                
                // Harga
                TextInput::make('harga')
                    ->label('Harga')
                    ->mask(RawJs::make('$money($input)'))
                    ->prefix('Rp')
                    ->stripCharacters(['.', ',', 'Rp', ' '])
                    ->numeric()
                    ->required(),
                
                // Fitur 3: QR Code (Perbaikan Bug & Menyembunyikan Field)
                // Mengubah dari Qr::make() ke TextInput::make() dan menyembunyikannya
                TextInput::make('qr_code')
                    ->label('QR Code Data')
                    ->maxLength(255)
                    ->default(null)
                    // HANYA FIELD INI YANG HARUS DISIMPAN SEBAGAI DATA QR CODE,
                    // dan field ini kita sembunyikan sesuai permintaan Anda.
                    ->hidden(), 

                // Keterangan
                Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->placeholder('Masukkan detail keterangan aset di sini...')
                    ->cols(4)
                    ->rows(4)
                    ->maxLength(100),
            ]);
    }
}
