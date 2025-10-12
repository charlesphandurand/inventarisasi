<?php

namespace App\Filament\Resources\Asets\Pages;

use App\Filament\Resources\Asets\AsetResource;
use App\Models\Aset;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAset extends CreateRecord
{
    protected static string $resource = AsetResource::class;

    // --- HOOK UNTUK MENGISI QR CODE SEBELUM DATA DISIMPAN ---
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Data QR Code diisi DENGAN URL unik ke halaman view aset ini.
        // ID aset belum tersedia di sini, jadi kita akan mengisi URL placeholder 
        // dan memperbarui nilai QR Code setelah record dibuat (di afterCreate).
        
        // Catatan: Nilai ini hanya placeholder. Nilai akhir akan diisi di afterCreate.
        $data['qr_code'] = 'processing-qr'; 
        
        return $data;
    }
    
    protected function handleRecordCreation(array $data): Model
    {
        $record = parent::handleRecordCreation($data);
        
        // Setelah record dibuat, kita punya ID-nya. Sekarang kita bisa membuat URL QR Code yang benar.
        
        // 1. Dapatkan URL ke halaman view aset yang baru dibuat.
        // Gunakan getUrl dari Resource, yang kita set default-nya ke 'view'
        $viewUrl = $this->getResource()::getUrl('view', ['record' => $record]);
        
        // 2. Isi field qr_code dengan URL lengkap.
        $record->qr_code = $viewUrl;
        $record->save();
        
        return $record;
    }
    // ----------------------------------------------------
    
    // Setelah selesai membuat record, kita navigasi ke halaman view
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
