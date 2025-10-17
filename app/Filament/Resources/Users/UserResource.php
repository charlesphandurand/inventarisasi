<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\GlobalSearch\GlobalSearchResult;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static string | \UnitEnum | null $navigationGroup = 'Manajemen User';

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
    
    /**
     * Helper function untuk mengecek apakah user memiliki peran Approver ATAU maker.
     */
    protected static function hasManagementAccess(): bool
    {
        return auth()->user()->hasAnyRole(['maker', 'approver']);
    }

    // Hanya Approver atau maker yang boleh melihat menu (Sidebar & Daftar)
    public static function canViewAny(): bool
    {
        return self::hasManagementAccess();
    }

    // Hanya Approver atau maker yang boleh membuat pengguna baru
    public static function canCreate(): bool
    {
        return self::hasManagementAccess();
    }

    // Hanya Approver atau maker yang boleh mengedit pengguna
    public static function canEdit(Model $record): bool
    {
        return self::hasManagementAccess();
    }

    // Hanya Approver atau maker yang boleh menghapus pengguna
    public static function canDelete(Model $record): bool
    {
        return self::hasManagementAccess();
    }

    // =================================================================
    // >>> IMPLEMENTASI GLOBAL SEARCH <<<
    // =================================================================
    
    /**
     * Tentukan kolom yang dapat dicari secara global.
     */
    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name', 
            'email',
        ];
    }

    /**
     * Judul hasil pencarian global.
     */
    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->name;
    }

    /**
     * Detail tambahan yang ditampilkan di hasil global search.
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        // Mendapatkan semua peran user (asumsi relasi 'roles' tersedia)
        // Jika relasi 'roles' tidak ada, hapus baris 'Peran'
        $roles = $record->roles->pluck('name')->map(fn($role) => ucfirst($role))->implode(', ');

        return [
            'Email' => $record->email,
            'Peran' => $roles ?: 'Tidak Ada Peran',
        ];
    }
    
    /**
     * Tentukan URL yang dituju saat hasil pencarian diklik.
     * Diarahkan ke halaman edit user tersebut.
     */
    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('edit', ['record' => $record]);
    }

    /**
     * Memastikan hanya pengguna yang berhak melihat resource ini yang dapat mencarinya.
     */
    public static function canGloballySearch(): bool
    {
        return static::canViewAny();
    }
    
    /**
     * Modifikasi query global search untuk eager loading relasi (misalnya 'roles').
     */
    public static function modifyGlobalSearchQuery(Builder $query, string $search): void
    {
        $query->with('roles');
    }

    // =================================================================
    // >>> END IMPLEMENTASI GLOBAL SEARCH <<<
    // =================================================================
}
