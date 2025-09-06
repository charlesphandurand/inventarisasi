<?php

namespace App\Filament\Resources\Asets\Pages;

use App\Filament\Resources\Asets\AsetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListAsets extends ListRecords
{
    protected static string $resource = AsetResource::class;

    protected function getHeaderActions(): array
    {
        $isAdmin = Auth::user()->hasRole('admin');
        
        return [
            // Hanya admin yang bisa create aset baru
            CreateAction::make()->visible(fn () => $isAdmin),
        ];
    }
}
