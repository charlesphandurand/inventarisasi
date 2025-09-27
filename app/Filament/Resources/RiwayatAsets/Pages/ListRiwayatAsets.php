<?php

namespace App\Filament\Resources\RiwayatAsets\Pages;

use App\Filament\Resources\RiwayatAsets\RiwayatAsetResource;
use Filament\Resources\Pages\ListRecords;

class ListRiwayatAsets extends ListRecords
{
    protected static string $resource = RiwayatAsetResource::class;

    protected static ?string $title = 'Riwayat Aset';
}


