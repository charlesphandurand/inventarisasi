<?php

namespace App\Filament\Resources\PengajuanPinjaman\Pages;

use App\Filament\Resources\PengajuanPinjaman\PengajuanPinjamanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPengajuanPinjaman extends EditRecord
{
    protected static string $resource = PengajuanPinjamanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
