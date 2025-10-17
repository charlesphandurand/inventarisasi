<?php

namespace App\Filament\Resources\Asets\Pages;

use App\Filament\Resources\Asets\AsetResource;
use Filament\Actions\CreateAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListAsets extends ListRecords
{
    protected static string $resource = AsetResource::class;

    protected function getHeaderActions(): array
    {
        $isAdmin = Auth::user()->hasAnyRole(['maker', 'approver']);

        return [
            CreateAction::make()->visible(fn () => $isAdmin),
        ];
    }
}
