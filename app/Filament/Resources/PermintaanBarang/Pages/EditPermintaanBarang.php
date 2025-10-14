<?php

namespace App\Filament\Resources\PermintaanBarang\Pages;

use App\Filament\Resources\PermintaanBarang\PermintaanBarangResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditPermintaanBarang extends EditRecord
{
    protected static string $resource = PermintaanBarangResource::class;

    protected function getHeaderActions(): array
    {
        $isAdmin = Auth::user()->hasAnyRole(['approver']);
        
        return [
            // Hanya admin yang bisa delete
            DeleteAction::make()->visible(fn () => $isAdmin),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
