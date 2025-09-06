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
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AsetResource extends Resource
{
    protected static ?string $model = Aset::class;
    
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'Manajemen Aset';  
    protected static ?string $recordTitleAttribute = 'nama_barang';

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

    public static function canViewAny(): bool
    {
        return auth()->user()->can('view asets');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create asets');
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->can('edit asets');
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->can('delete asets');
    }
}
