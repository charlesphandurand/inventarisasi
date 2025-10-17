<?php

namespace App\Filament\Resources\PengajuanPinjaman\Pages;

use App\Filament\Resources\PengajuanPinjaman\PengajuanPinjamanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Aset;

class EditPengajuanPinjaman extends EditRecord
{
    protected static string $resource = PengajuanPinjamanResource::class;

    // Hilangkan manajemen stok di level halaman; ditangani oleh model event

    protected function getHeaderActions(): array
    {
        $isAdmin = Auth::user()->hasAnyRole(['maker', 'approver']);
        
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