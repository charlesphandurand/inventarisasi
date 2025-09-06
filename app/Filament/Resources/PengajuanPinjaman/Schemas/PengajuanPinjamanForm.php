<?php

namespace App\Filament\Resources\PengajuanPinjaman\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use App\Models\Aset;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class PengajuanPinjamanForm
{
    public static function configure(Schema $schema): Schema
    {
        $isAdmin = Auth::user()->hasRole('admin');

        return $schema
            ->components([
                Select::make('aset_id')
                    ->label('Nama Barang')
                    ->relationship(name: 'aset', titleAttribute: 'nama_barang')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, $state) {
                        if ($state) {
                            $aset = Aset::find($state);
                            if ($aset) {
                                $set('sisa_barang', $aset->sisa_barang);
                            }
                        } else {
                            $set('sisa_barang', null);
                        }
                    }),

                TextInput::make('sisa_barang')
                    ->label('Sisa Barang Tersedia')
                    ->disabled()
                    ->numeric()
                    ->dehydrated(false)
                    ->default(function ($get) {
                        $asetId = $get('aset_id');
                        if ($asetId) {
                            $aset = Aset::find($asetId);
                            return $aset ? $aset->sisa_barang : 0;
                        }
                        return 0;
                    }),
                
                TextInput::make('jumlah_pinjam')
                    ->label('Jumlah Pinjam')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->live()
                    ->rules([
                        'required',
                        'numeric',
                        'min:1',
                        function (callable $get) {
                            return function (string $attribute, $value, callable $fail) use ($get) {
                                $sisaBarang = (int) $get('sisa_barang');
                                if ($value > $sisaBarang) {
                                    $fail("Jumlah pinjam tidak boleh lebih dari {$sisaBarang} barang.");
                                }
                                if ($value <= 0) {
                                    $fail("Jumlah pinjam harus lebih dari 0 barang.");
                                }
                            };
                        },
                    ]),
                
                // Field status hanya untuk admin dan hanya saat edit
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'diajukan' => 'Diajukan',
                        'disetujui' => 'Disetujui',
                        'ditolak' => 'Ditolak',
                    ])
                    ->default('diajukan')
                    ->visible(fn () => $isAdmin)
                    ->required(),
            ]);
    }
}