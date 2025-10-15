<?php

namespace App\Filament\Resources\PengajuanPinjaman\Pages;

use App\Filament\Resources\PengajuanPinjaman\PengajuanPinjamanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListPengajuanPinjaman extends ListRecords
{
    protected static string $resource = PengajuanPinjamanResource::class;

    // Tambahkan baris ini
    protected static ?string $title = 'Pinjaman Barang'; 

    protected function getHeaderActions(): array
    {
        // Semua user (admin dan user biasa) bisa create pengajuan pinjaman
        return [
            CreateAction::make(),
        ];
    }
}
