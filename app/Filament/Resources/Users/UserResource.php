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
use Illuminate\Database\Eloquent\Model;

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
}
