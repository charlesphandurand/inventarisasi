<?php

namespace App\Filament\Resources\PermintaanBarang;

use App\Filament\Resources\PermintaanBarang\Pages\CreatePermintaanBarang;
use App\Filament\Resources\PermintaanBarang\Pages\EditPermintaanBarang;
use App\Filament\Resources\PermintaanBarang\Pages\ListPermintaanBarang;
use App\Filament\Resources\PermintaanBarang\Schemas\PermintaanBarangForm;
use App\Filament\Resources\PermintaanBarang\Tables\PermintaanBarangTable;
use App\Models\PengajuanPinjaman; // Menggunakan model yang sama
use Filament\Schemas\Schema;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

class PermintaanBarangResource extends Resource
{
    protected static ?string $model = PengajuanPinjaman::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-gift';

    protected static ?string $recordTitleAttribute = 'permintaan barang';
    
    protected static ?string $navigationLabel = 'Permintaan Barang (ATK)'; 
    protected static UnitEnum|string|null $navigationGroup = 'Manajemen Aset';
    protected static ?string $slug = 'permintaan-barang-atk';
    protected static ?int $navigationSort = 2; // Memberi urutan agar muncul setelah Pinjaman

    public static function form(Schema $schema): Schema
    {
        return PermintaanBarangForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PermintaanBarangTable::configure($table);
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
            'index' => ListPermintaanBarang::route('/'),
            'create' => CreatePermintaanBarang::route('/create'),
            'edit' => EditPermintaanBarang::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        // FILTER GLOBAL KRITIS: Hanya tampilkan pengajuan yang asetnya adalah ATK (is_atk = 1)
        $query->whereHas('aset', function (Builder $subQuery) {
            $subQuery->where('is_atk', 1);
        });

        // Jika user bukan admin, hanya tampilkan permintaan miliknya sendiri
        if (!Auth::user()->hasAnyRole(['approver'])) {
            $query->where('user_id', Auth::id());
        }
        
        return $query;
    }

    // Hak akses diwarisi dari PengajuanPinjamanResource karena modelnya sama
    public static function canViewAny(): bool
    {
        return auth()->user()->can('view pengajuan');
    }

    public static function canView(Model $record): bool
    {
        $user = auth()->user();
        return $user->can('view pengajuan') && ($user->hasAnyRole(['approver']) || $record->user_id === $user->id);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create pengajuan');
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        return $user->can('edit pengajuan') && ($user->hasAnyRole(['approver']) || $record->user_id === $user->id);
    }
    
    public static function canDelete(Model $record): bool
    {
        return auth()->user()->hasAnyRole(['approver']) && auth()->user()->can('delete pengajuan');
    }
}
