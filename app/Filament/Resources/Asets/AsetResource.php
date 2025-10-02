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
use Illuminate\Support\Facades\Auth;

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

    // Fungsi untuk memeriksa apakah aset dapat dilihat (list table)
    public static function canViewAny(): bool
    {
        // Semua pengguna yang memiliki permission 'view asets' dapat melihat daftar aset.
        return Auth::user()->can('view asets');
    }

    // Fungsi untuk memeriksa apakah aset dapat dibuat
    public static function canCreate(): bool
    {
        $user = Auth::user();
        // Hanya Admin dan Approver yang dapat membuat aset
        return $user->hasAnyRole(['admin', 'approver']) && $user->can('create asets');
    }

    // Fungsi untuk memeriksa apakah aset dapat diedit
    public static function canEdit(Model $record): bool
    {
        $user = Auth::user();
        // Hanya Admin dan Approver yang dapat mengedit aset
        return $user->hasAnyRole(['admin', 'approver']) && $user->can('edit asets');
    }

    // Fungsi untuk memeriksa apakah aset dapat dihapus
    public static function canDelete(Model $record): bool
    {
        $user = Auth::user();
        // Hanya Admin dan Approver yang dapat menghapus aset
        return $user->hasAnyRole(['admin', 'approver']) && $user->can('delete asets');
    }
}
