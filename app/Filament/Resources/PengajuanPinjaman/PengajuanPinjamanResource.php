<?php

namespace App\Filament\Resources\PengajuanPinjaman;

use App\Filament\Resources\PengajuanPinjaman\Pages\CreatePengajuanPinjaman;
use App\Filament\Resources\PengajuanPinjaman\Pages\EditPengajuanPinjaman;
use App\Filament\Resources\PengajuanPinjaman\Pages\ListPengajuanPinjaman;
use App\Filament\Resources\PengajuanPinjaman\Schemas\PengajuanPinjamanForm;
use App\Filament\Resources\PengajuanPinjaman\Tables\PengajuanPinjamanTable;
use Filament\Schemas\Schema;
use App\Models\PengajuanPinjaman;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

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
}