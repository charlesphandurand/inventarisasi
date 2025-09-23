<?php

namespace App\Filament\Resources\PengajuanPinjaman\Schemas;

use App\Models\Aset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

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
                            ->get()
                            ->mapWithKeys(function ($aset) {
                                $lokasi = $aset->lokasi ?: 'Tanpa Lokasi';
                                return [$aset->id => "{$aset->nama_barang} - {$lokasi} (Stok: {$aset->jumlah_barang})"];
                            })
                    )
                    ->required()
                    ->searchable()
                    ->reactive(),
               
                // Kembalikan dropdown status untuk admin saat edit
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'diajukan' => 'Diajukan',
                        'disetujui' => 'Disetujui',
                        'ditolak' => 'Ditolak',
                        'dikembalikan' => 'Dikembalikan',
                    ])
                    ->default('diajukan')
                    ->visible(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord && Auth::user()?->hasRole('admin'))
                    ->required()
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        if ($state === 'disetujui') {
                            $aset = Aset::find($get('aset_id'));
                            $jumlah = (int) $get('jumlah_pinjam');
                            $stok = (int) ($aset->jumlah_barang ?? 0);
                            if ($aset && $jumlah > $stok) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Gagal Disetujui')
                                    ->body('Jumlah barang tidak mencukupi untuk pengajuan ini.')
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
                                if ((int) $value > $stok) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Jumlah Melebihi Stok')
                                        ->body("Tersedia: {$stok}.")
                                        ->danger()
                                        ->send();
                                    $fail('');
                                }
                            };
                        },
                    ]),

                // Keterangan live stok dipindahkan ke bawah field jumlah pinjam
                Placeholder::make('live_stock_info')
                    ->label('Informasi Stok')
                    ->content(function (Get $get) {
                        $asetId = $get('aset_id');
                        $jumlahPinjam = (int) $get('jumlah_pinjam');
                        
                        if (!$asetId) {
                            return 'Pilih aset terlebih dahulu untuk melihat informasi stok';
                        }
                        
                        $aset = Aset::find($asetId);
                        if (!$aset) {
                            return 'Aset tidak ditemukan';
                        }
                        
                        $stokTersedia = $aset->jumlah_barang;
                        $lokasi = $aset->lokasi ?: 'Tanpa Lokasi';
                        $sisaStok = $stokTersedia - $jumlahPinjam;
                        
                        $info = "📦 {$aset->nama_barang}\n📍 Lokasi: {$lokasi}\n📊 Stok Tersedia: {$stokTersedia}";
                        
                        if ($jumlahPinjam > 0) {
                            $status = $sisaStok >= 0 ? '✅ Stok Cukup' : '❌ Stok Tidak Cukup';
                            $info .= "\n📋 Sisa Setelah Dipinjam: {$sisaStok} ({$status})";
                        }
                        
                        return $info;
                    })
                    ->visible(fn (Get $get) => $get('aset_id') !== null),

                // Field tanggal_pinjam, tanggal_kembali_rencana, dan keperluan dihapus sesuai permintaan
            ]);
    }
}