<?php

namespace App\Filament\Resources\Asets;

use App\Filament\Resources\Asets\Pages\CreateAset;
use App\Filament\Resources\Asets\Pages\EditAset;
use App\Filament\Resources\Asets\Pages\ListAsets;
use App\Filament\Resources\Asets\Schemas\AsetForm;
use App\Filament\Resources\Asets\Tables\AsetsTable;
use Filament\Forms\Components\MarkdownEditor;
use App\Models\Aset;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AsetResource extends Resource
{
    protected static ?string $model = Aset::class;
    
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'nama_barang'; // Perbaikan di sini (search publik)

    public static function form(Schema $schema): Schema
    {
        return AsetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AsetsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAsets::route('/'),
            'create' => CreateAset::route('/create'),
            'edit' => EditAset::route('/{record}/edit'),
        ];
    }
}
