<?php

namespace App\Filament\Resources\PengajuanPinjaman\Pages;

use App\Filament\Resources\PengajuanPinjaman\PengajuanPinjamanResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Aset;
use Carbon\Carbon;

class CreatePengajuanPinjaman extends CreateRecord
{
    protected static string $resource = PengajuanPinjamanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set user_id otomatis
        $data['user_id'] = Auth::id();
        // Pastikan tanggal_pengajuan memakai waktu sistem (timezone app)
        $data['tanggal_pengajuan'] = \Carbon\Carbon::now()->setTimezone(config('app.timezone'));
        
        return $data;
    }

    // Aksi pengurangan stok di sini dihapus.
    // Stok sekarang akan berkurang hanya setelah pengajuan disetujui
    // oleh admin melalui action terpisah.
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}