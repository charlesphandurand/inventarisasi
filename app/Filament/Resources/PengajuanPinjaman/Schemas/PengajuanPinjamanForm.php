<?php

namespace App\Filament\Resources\PengajuanPinjaman\Schemas;

use App\Models\Aset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class PengajuanPinjamanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('aset_id')
                    ->label('Nama Aset')
                    ->options(
                        Aset::query()
                            ->whereNotNull('nama_barang')
                            // INI FILTER UNTUK MENGHILANGKAN ASET BERSTATUS ATK (1)
                            ->where('is_atk', '!=', 1) 
                            ->get()
                            ->mapWithKeys(function ($aset) {
                                $lokasi = $aset->lokasi ?: 'Tanpa Lokasi';
                                return [$aset->id => "{$aset->nama_barang} - {$lokasi} (Stok: {$aset->jumlah_barang})"];
                            })
                    )
                    ->required()
                    ->searchable()
                    ->reactive(),

                // Dropdown Status (Hanya visible untuk Approver saat Edit)
                Select::make('status')
                    ->label('Status Persetujuan')
                    ->options([
                        'diajukan' => 'Diajukan',
                        'disetujui' => 'Disetujui',
                        'ditolak' => 'Ditolak',
                        'dikembalikan' => 'Dikembalikan',
                    ])
                    ->default('diajukan')
                    ->visible(fn ($livewire) => $livewire instanceof EditRecord && Auth::user()?->hasAnyRole(['maker', 'approver']))
                    ->required()
                    ->afterStateUpdated(function ($state, callable $set, Get $get) {
                        if ($state === 'disetujui') {
                            $aset = Aset::find($get('aset_id'));
                            $jumlah = (int) $get('jumlah_pinjam');
                            $stok = (int) ($aset->jumlah_barang ?? 0);

                            if ($aset && $jumlah > $stok) {
                                Notification::make()
                                    ->title('Gagal Disetujui')
                                    ->body("Stok saat ini hanya {$stok}. Jumlah pinjaman ({$jumlah}) melebihi stok yang tersedia.")
                                    ->danger()
                                    ->send();
                                $set('status', 'diajukan');
                            }
                        }
                    }),

                TextInput::make('jumlah_pinjam')
                    ->label('Jumlah Pinjam')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->reactive()
                    ->rules([
                        function (Get $get) {
                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                $aset = Aset::find($get('aset_id'));
                                if (!$aset) {
                                    return;
                                }
                                $stok = (int) $aset->jumlah_barang;
                                $jumlahPinjam = (int) $value;

                                if ($jumlahPinjam > $stok) {
                                    $fail("Jumlah pinjam ($jumlahPinjam) melebihi stok tersedia ($stok).");
                                    
                                    Notification::make()
                                        ->title('Jumlah Melebihi Stok')
                                        ->body("Stok tersedia: {$stok}. Anda meminta {$jumlahPinjam}.")
                                        ->danger()
                                        ->send();
                                }
                            };
                        },
                    ]),

                // Placeholder Informasi Stok dengan format Markdown yang lebih rapi
                Placeholder::make('live_stock_info')
                    ->label('Informasi Stok Aset Terpilih')
                    ->content(function (Get $get) {
                        $asetId = $get('aset_id');
                        $jumlahPinjam = (int) $get('jumlah_pinjam');
                        
                        if (!$asetId) {
                            return 'Pilih aset untuk melihat detail stok dan lokasi.';
                        }
                        
                        $aset = Aset::find($asetId);
                        if (!$aset) {
                            return 'Aset tidak ditemukan.';
                        }
                        
                        $stokTersedia = (int) $aset->jumlah_barang;
                        $lokasi = $aset->lokasi ?: 'Tanpa Lokasi';
                        $sisaStok = $stokTersedia - $jumlahPinjam;
                        
                        $info = "**📦 {$aset->nama_barang}**\n\n";
                        $info .= "- 📍 **Lokasi:** {$lokasi}\n";
                        $info .= "- 📊 **Stok Tersedia:** {$stokTersedia} unit\n";
                        
                        if ($jumlahPinjam > 0) {
                            $status = $sisaStok >= 0 ? 'CUKUP' : 'TIDAK CUKUP';
                            $info .= "- 📋 **Sisa Setelah Dipinjam:** {$sisaStok} unit (**{$status}**)";
                        }
                        
                        return $info;
                    })
                    ->markdown()
                    ->visible(fn (Get $get) => $get('aset_id') !== null),
            ]);
    }
}
