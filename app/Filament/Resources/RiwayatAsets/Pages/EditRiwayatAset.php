<?php

namespace App\Filament\Resources\RiwayatAsets\Pages;

use App\Filament\Resources\RiwayatAsets\RiwayatAsetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRiwayatAset extends EditRecord
{
    protected static string $resource = RiwayatAsetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
