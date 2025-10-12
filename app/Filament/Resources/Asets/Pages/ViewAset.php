<?php

namespace App\Filament\Resources\Asets\Pages;

use App\Filament\Resources\Asets\AsetResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

// Halaman View ini memungkinkan aset dilihat setelah dibuat/diedit.
// Class ini yang dicari oleh AsetResource, sehingga jika hilang, akan menyebabkan Class not found.
class ViewAset extends ViewRecord
{
    protected static string $resource = AsetResource::class;
    
    // Secara default, halaman ViewRecord sudah bagus, tapi kita bisa menambahkan
    // action untuk kembali ke halaman Index atau Edit
    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
