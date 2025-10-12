<?php

namespace App\Filament\Resources\Asets\Pages;

use App\Filament\Resources\Asets\AsetResource;
use App\Models\Aset;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAset extends EditRecord
{
    protected static string $resource = AsetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    
    // --- HOOK UNTUK MENGISI QR CODE SEBELUM DATA DISIMPAN (UPDATE) ---
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Hanya update jika qr_code masih kosong atau placeholder lama
        if (empty($this->record->qr_code) || $this->record->qr_code === 'processing-qr') {
            
            // 1. Dapatkan URL ke halaman view aset yang sedang diedit.
            // Gunakan getUrl dari Resource, yang kita set default-nya ke 'view'
            $viewUrl = $this->getResource()::getUrl('view', ['record' => $this->record]);
            
            // 2. Isi field qr_code dengan URL lengkap.
            $data['qr_code'] = $viewUrl;
        }

        return $data;
    }
    // ----------------------------------------------------
    
    // Setelah selesai mengedit record, kita navigasi ke halaman view
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
