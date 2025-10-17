<?php

namespace App\Filament\Resources\RiwayatAsets;

use App\Filament\Resources\RiwayatAsets\Tables\RiwayatAsetsTable;
use App\Models\RiwayatAset;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\GlobalSearch\GlobalSearchResult;

class RiwayatAsetResource extends Resource
{
    protected static ?string $model = RiwayatAset::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;
    protected static ?string $navigationLabel = 'Riwayat Aset';
    protected static ?string $slug = 'riwayat-aset';
    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Aset';
    protected static ?int $navigationSort = 2; 

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
        // Hanya izinkan pengguna dengan peran yang ditentukan untuk melihat resource ini.
        return auth()->user()->hasRole('approver') || auth()->user()->hasRole('maker') || auth()->user()->hasRole('user');
    }
    
    // =================================================================
    // >>> IMPLEMENTASI GLOBAL SEARCH <<<
    // =================================================================

    /**
     * Konfigurasi global search untuk mencari di kolom yang relevan (termasuk relasi).
     * Disertakan: id, tipe, lokasi_sebelum, lokasi_sesudah, keterangan, serta nama aset dan user.
     */
    public static function getGloballySearchableAttributes(): array
    {
        return [
            'id',
            'aset.nama_barang', // Relasi ke nama aset
            'user.name',       // Relasi ke nama pengguna
            'tipe',            // Tipe Transaksi (misalnya, Masuk, Keluar)
            'lokasi_sebelum',
            'lokasi_sesudah',
            'keterangan',
        ];
    }

    /**
     * Judul yang ditampilkan di hasil global search
     */
    public static function getGlobalSearchResultTitle(Model $record): string
    {
        // Pastikan relasi 'aset' sudah dimuat (disediakan oleh modifyGlobalSearchQuery)
        $namaAset = $record->aset->nama_barang ?? 'Aset Tidak Ditemukan';
        $userName = $record->user->name ?? 'Pengguna Tidak Dikenal';
        
        // Judul fokus pada ID, Aset, Tipe Transaksi, dan Pengguna
        $tipe = $record->tipe ?? 'Tipe Transaksi Tidak Diketahui';
        return "Riwayat ID #{$record->id}: {$tipe} Aset '{$namaAset}' oleh {$userName}";
    }
    
    /**
     * Mengembalikan objek GlobalSearchResult secara penuh
     */
    public static function getGlobalSearchResult(Model $record): GlobalSearchResult
    {
        $namaAset = $record->aset->nama_barang ?? 'Aset Tidak Ditemukan';
        $userName = $record->user->name ?? 'Pengguna Tidak Dikenal';
        $tipe = $record->tipe ?? 'Tipe Transaksi Tidak Diketahui';
        
        return new GlobalSearchResult(
            title: "Riwayat ID #{$record->id}: {$tipe} Aset '{$namaAset}' oleh {$userName}",
            url: static::getUrl('index'), // Mengarahkan ke halaman daftar (index)
            details: static::getGlobalSearchResultDetails($record),
        );
    }

    /**
     * Detail tambahan yang ditampilkan di hasil global search
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        // Menyertakan field-field utama untuk informasi cepat
        $stokSebelum = $record->stok_sebelum ?? 'N/A';
        $stokSesudah = $record->stok_sesudah ?? 'N/A';
        $jumlahPerubahan = $record->jumlah_perubahan ?? 'N/A';

        return [
            'Nama Aset' => $record->aset->nama_barang ?? 'N/A',
            'Oleh Pengguna' => $record->user->name ?? 'N/A',
            'Tipe Transaksi' => $record->tipe ?? 'N/A',
            'Perubahan Stok' => "{$stokSebelum} → {$stokSesudah} ({$jumlahPerubahan})",
            'Keterangan' => \Illuminate\Support\Str::limit($record->keterangan ?? 'Tidak ada keterangan', 50),
            'Waktu' => $record->created_at->format('d/m/Y H:i:s'),
        ];
    }
    
    /**
     * Modifikasi query global search untuk memastikan relasi dimuat (eager loading)
     */
    public static function modifyGlobalSearchQuery(Builder $query, string $search): void
    {
        // Memastikan relasi 'user' dan 'aset' dimuat
        $query->with(['user', 'aset']);
    }

    /**
     * Memastikan global search aktif
     */
    public static function canGloballySearch(): bool
    {
        return static::canViewAny();
    }
    
    /**
     * Query global search menggunakan query resource yang sudah ada
     */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return static::getEloquentQuery();
    }
    
    /**
     * URL hasil global search
     * Fungsi ini sekarang diabaikan karena logika URL dipindahkan ke getGlobalSearchResult()
     */
    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return null;
    }
    
    // =================================================================
    // >>> END IMPLEMENTASI GLOBAL SEARCH <<<
    // =================================================================
}
