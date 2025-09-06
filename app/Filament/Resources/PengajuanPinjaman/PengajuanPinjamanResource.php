<?php

namespace App\Filament\Resources\PengajuanPinjaman;

use App\Filament\Resources\PengajuanPinjaman\Pages\CreatePengajuanPinjaman;
use App\Filament\Resources\PengajuanPinjaman\Pages\EditPengajuanPinjaman;
use App\Filament\Resources\PengajuanPinjaman\Pages\ListPengajuanPinjaman;
use App\Filament\Resources\PengajuanPinjaman\Schemas\PengajuanPinjamanForm;
use App\Filament\Resources\PengajuanPinjaman\Tables\PengajuanPinjamanTable;
use App\Models\PengajuanPinjaman;
use Filament\Schemas\Schema; 
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

class PengajuanPinjamanResource extends Resource
{
    protected static ?string $model = PengajuanPinjaman::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-plus';

    protected static ?string $recordTitleAttribute = 'pengajuan pinjaman';
    
    protected static ?string $navigationLabel = 'Pengajuan Pinjaman'; 
    protected static UnitEnum|string|null $navigationGroup = 'Manajemen Aset';
    protected static ?string $slug = 'pengajuan-pinjaman';

    public static function form(Schema $schema): Schema
    {
        return PengajuanPinjamanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PengajuanPinjamanTable::configure($table);
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
            'index' => ListPengajuanPinjaman::route('/'),
            'create' => CreatePengajuanPinjaman::route('/create'),
            'edit' => EditPengajuanPinjaman::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        // Jika user bukan admin, hanya tampilkan pengajuan miliknya sendiri
        if (!Auth::user()->hasRole('admin')) {
            $query->where('user_id', Auth::id());
        }
        
        return $query;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->can('view pengajuan');
    }

    public static function canView(Model $record): bool
    {
        $user = auth()->user();
        // Admin bisa lihat semua, user hanya lihat miliknya sendiri
        return $user->can('view pengajuan') && ($user->hasRole('admin') || $record->user_id === $user->id);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create pengajuan');
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        // Admin bisa edit semua, user hanya edit miliknya sendiri
        return $user->can('edit pengajuan') && ($user->hasRole('admin') || $record->user_id === $user->id);
    }
    
    public static function canDelete(Model $record): bool
    {
        // Hanya admin yang bisa delete
        return auth()->user()->hasRole('admin') && auth()->user()->can('delete pengajuan');
    }
}