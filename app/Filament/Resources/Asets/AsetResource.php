<?php

namespace App\Filament\Resources\Asets;

use App\Filament\Resources\Asets\Pages\CreateAset;
use App\Filament\Resources\Asets\Pages\EditAset;
use App\Filament\Resources\Asets\Pages\ListAsets;
use App\Filament\Resources\Asets\Pages\ViewAset;
// use App\Filament\Resources\Asets\Pages\PrintQrBulk; // <<< REFERENSI DIHAPUS
use App\Filament\Resources\Asets\Schemas\AsetForm;
use App\Filament\Resources\Asets\Tables\AsetsTable;

// --- IMPORTS YANG DIPERLUKAN UNTUK CUSTOM BULK ACTION DAN FORM ---
use Filament\Tables\Actions\BulkAction; 
use Illuminate\Support\Collection;
use Filament\Forms; // Diperlukan untuk CheckboxList
use Filament\Notifications\Notification; // Diperlukan untuk notifikasi sukses
// ------------------------------------------------------------------

use Filament\Resources\Pages\Page;
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

    // --- DEFINISI HALAMAN (REFERENSI PrintQrBulk DIHAPUS) ---
    public static function getPages(): array
    {
        return [
            'index' => ListAsets::route('/'),
            'create' => CreateAset::route('/create'),
            'edit' => EditAset::route('/{record}/edit'),
            'view' => ViewAset::route('/{record}'), // Rute 'view' ada di sini
        ];
    }
    // ------------------------------------------

    // --- CUSTOM BULK ACTION DENGAN PILIHAN KOLOM ---
    public static function getTableBulkActions(): array
    {
        return [
            BulkAction::make('exportCustom')
                ->label('Ekspor Aset (Custom Kolom)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->form([
                    // Menggunakan CheckboxList untuk memilih kolom
                    Forms\Components\CheckboxList::make('export_columns')
                        ->label('Pilih Kolom untuk Diekspor')
                        // Mengambil daftar kolom yang tersedia
                        ->options(static::getExportableColumns()) 
                        // Mengatur semua kolom terpilih secara default
                        ->default(array_keys(static::getExportableColumns())) 
                        ->required(),
                ])
                ->action(function (Collection $records, array $data) {
                    $selectedColumns = $data['export_columns'];
                    $exportData = [];

                    // Proses Data Berdasarkan Kolom Terpilih
                    foreach ($records as $record) {
                        $rowData = [];
                        foreach ($selectedColumns as $columnName) {
                            // Ambil nilai atribut. Sesuaikan jika perlu relasi (misalnya: $record->relation->attribute)
                            $rowData[static::getExportableColumns()[$columnName]] = $record->getAttribute($columnName);
                        }
                        $exportData[] = $rowData;
                    }
                    
                    // --- SIMULASI EKSPOR ---
                    // DI SINI ADALAH TEMPAT UNTUK MEMANGGIL KELAS EKSPOR ANDA 
                    // (misalnya Laravel Excel/DomPDF) dengan data $exportData.
                    
                    // Contoh Notifikasi Sukses
                    Notification::make()
                        ->title('Ekspor berhasil disiapkan!')
                        ->body('Data aset (' . count($exportData) . ' baris) telah diproses untuk ekspor dengan kolom: ' . implode(', ', $selectedColumns))
                        ->success()
                        ->send();

                    // Di dunia nyata, ini akan me-return respons download
                    // return response()->json(['message' => 'Export completed', 'data' => $exportData]);
                })
                ->deselectRecordsAfterCompletion(),
        ];
    }
    
    // --- FUNGSI BARU UNTUK MENDAPATKAN KOLOM YANG BISA DIEKSPOR ---
    protected static function getExportableColumns(): array
    {
        // PENTING: Sesuaikan array ini dengan nama kolom AKTUAL di database/model Anda.
        // Key: Nama Kolom (atribut Model), Value: Label yang Ditampilkan di Checkbox
        return [
            'nama_barang' => 'Nama Barang',
            'kode_aset' => 'Kode Aset',
            'kondisi' => 'Kondisi Aset',
            'lokasi' => 'Lokasi Penempatan',
            'harga_beli' => 'Harga Beli',
            'tanggal_beli' => 'Tanggal Pembelian',
            // Tambahkan kolom lain seperti relasi (misal: 'user_id' => 'Penanggung Jawab')
            // Untuk relasi kompleks, mungkin perlu penyesuaian logika di action()
        ];
    }
    // -------------------------------------------------------------
    
    public static function canViewAny(): bool
    {
        return Auth::user()->can('view asets');
    }

    public static function canCreate(): bool
    {
        $user = Auth::user();
        return $user->hasAnyRole(['admin', 'approver']) && $user->can('create asets');
    }

    public static function canEdit(Model $record): bool
    {
        $user = Auth::user();
        return $user->hasAnyRole(['admin', 'approver']) && $user->can('edit asets');
    }

    public static function canDelete(Model $record): bool
    {
        $user = Auth::user();
        return $user->hasAnyRole(['admin', 'approver']) && $user->can('delete asets');
    }
}
