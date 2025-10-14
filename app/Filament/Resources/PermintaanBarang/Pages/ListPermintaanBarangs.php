<?php

namespace App\Filament\Resources\PermintaanBarang\Pages;

use App\Filament\Resources\PermintaanBarang\PermintaanBarangResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPermintaanBarang extends ListRecords
{
    protected static string $resource = PermintaanBarangResource::class;

    protected static ?string $title = 'Permintaan Barang (Khusus ATK)'; 

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
