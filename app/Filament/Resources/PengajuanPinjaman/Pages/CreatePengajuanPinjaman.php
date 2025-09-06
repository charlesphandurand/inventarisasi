<?php

namespace App\Filament\Resources\PengajuanPinjaman\Pages;

use App\Filament\Resources\PengajuanPinjaman\PengajuanPinjamanResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class CreatePengajuanPinjaman extends CreateRecord
{
    protected static string $resource = PengajuanPinjamanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set default values untuk user yang sedang login dengan timezone WITA
        $data['user_id'] = Auth::id();
        $data['tanggal_pengajuan'] = Carbon::now('Asia/Makassar');
        $data['status'] = 'diajukan';

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
