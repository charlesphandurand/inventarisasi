<?php

namespace App\Filament\Resources\PengajuanPinjaman\Pages;

use App\Filament\Resources\PengajuanPinjaman\PengajuanPinjamanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditPengajuanPinjaman extends EditRecord
{
    protected static string $resource = PengajuanPinjamanResource::class;

    protected function getHeaderActions(): array
    {
        $isAdmin = Auth::user()->hasRole('admin');
        
        return [
            // Hanya admin yang bisa delete
            DeleteAction::make()->visible(fn () => $isAdmin),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Set nilai default untuk sisa_barang berdasarkan aset yang dipilih
        if (isset($data['aset_id'])) {
            $aset = \App\Models\Aset::find($data['aset_id']);
            if ($aset) {
                $data['sisa_barang'] = $aset->sisa_barang;
            }
        }
        
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
