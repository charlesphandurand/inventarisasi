<?php

namespace App\Filament\Resources\PermintaanBarang\Pages;

use App\Filament\Resources\PermintaanBarang\PermintaanBarangResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class CreatePermintaanBarang extends CreateRecord
{
    protected static string $resource = PermintaanBarangResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set user_id otomatis
        $data['user_id'] = Auth::id();
        // Pastikan tanggal_pengajuan memakai waktu sistem (timezone app)
        $data['tanggal_pengajuan'] = Carbon::now()->setTimezone(config('app.timezone'));
        
        return $data;
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
