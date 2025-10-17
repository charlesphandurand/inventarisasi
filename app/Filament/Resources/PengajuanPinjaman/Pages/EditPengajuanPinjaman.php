<?php

namespace App\Filament\Resources\PengajuanPinjaman\Pages;

use App\Filament\Resources\PengajuanPinjaman\PengajuanPinjamanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Aset;
use Illuminate\Database\Eloquent\Model; // Diperlukan untuk type hint record

class EditPengajuanPinjaman extends EditRecord
{
    protected static string $resource = PengajuanPinjamanResource::class;

    protected function getHeaderActions(): array
    {
        // Mendapatkan record yang sedang diedit
        /** @var Model $record */
        $record = $this->getRecord();
        $user = Auth::user();
        $isUserLoggedIn = (bool) $user; // Memastikan pengguna terautentikasi (meskipun seharusnya selalu)
        
        // Logika Pengecekan Akses Hapus (Disempurnakan sesuai permintaan):
        // Hapus hanya diizinkan JIKA status masih 'diajukan' DAN pengajuan milik user yang sedang login.
        // Role Admin/Maker/Approver tidak lagi mendapatkan pengecualian akses hapus di luar kondisi ini.

        $canDelete = false;

        if ($isUserLoggedIn) {
            $canDelete = $record->user_id === $user->id && $record->status === 'diajukan';
        }

        return [
            DeleteAction::make()
                // Aksi Hapus terlihat HANYA jika status adalah 'diajukan' dan dimiliki oleh user yang bersangkutan.
                // Ini berlaku untuk semua role (user biasa, maker, approver).
                ->visible(fn () => $canDelete),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
