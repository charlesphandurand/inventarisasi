<?php

namespace App\Filament\Resources\PengajuanPinjaman\Pages;

use App\Filament\Resources\PengajuanPinjaman\PengajuanPinjamanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPengajuanPinjaman extends ListRecords
{
    protected static string $resource = PengajuanPinjamanResource::class;

     // Tambahkan baris ini
     protected static ?string $title = 'Pengajuan Pinjaman'; 

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
