<?php

namespace App\Filament\Resources\RiwayatAsets;

use App\Filament\Resources\RiwayatAsets\Tables\RiwayatAsetsTable;
use App\Models\RiwayatAset;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RiwayatAsetResource extends Resource
{
    protected static ?string $model = RiwayatAset::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;
    protected static ?string $navigationLabel = 'Riwayat Aset';
    protected static ?string $slug = 'riwayat-aset';
    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Aset';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return RiwayatAsetsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRiwayatAsets::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->can('view asets');
    }
}


