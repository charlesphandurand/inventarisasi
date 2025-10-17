<?php

namespace App\Filament\Resources\PermintaanBarang;

use App\Filament\Resources\PermintaanBarang\Pages\CreatePermintaanBarang;
use App\Filament\Resources\PermintaanBarang\Pages\EditPermintaanBarang;
use App\Filament\Resources\PermintaanBarang\Pages\ListPermintaanBarang;
use App\Filament\Resources\PermintaanBarang\Schemas\PermintaanBarangForm;
use App\Filament\Resources\PermintaanBarang\Tables\PermintaanBarangTable;
use App\Models\PengajuanPinjaman; // Menggunakan model yang sama
use App\Models\Aset; // Diperlukan untuk pengurangan stok
use App\Models\RiwayatAset; // Diperlukan untuk mencatat riwayat
// use Filament\Forms\Form; // Dihapus karena konflik, kembali ke Schema yang sebelumnya bekerja.
use Filament\Schemas\Schema; // **Perbaikan: Kembali ke Schema (v2/early v3) untuk menghindari konflik type-hint.**
// use Filament\Forms\Components\Select; // Dihapus karena tidak digunakan
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB; // Diperlukan untuk transaksi
use Carbon\Carbon; // Diperlukan untuk tanggal
use Filament\Notifications\Notification; // Diperlukan untuk notifikasi

class PermintaanBarangResource extends Resource
{
    protected static ?string $model = PengajuanPinjaman::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-gift';

    protected static ?string $recordTitleAttribute = 'id';
    
    protected static ?string $navigationLabel = 'Permintaan ATK'; 
    protected static UnitEnum|string|null $navigationGroup = 'Manajemen Aset';
    protected static ?string $slug = 'permintaan-barang-atk';
    protected static ?int $navigationSort = 2; // Memberi urutan agar muncul setelah Pinjaman

    // Mengganti kembali ke Schema $schema (sesuai kode yang sebelumnya tidak error)
    public static function form(Schema $schema): Schema 
    {
        // Pastikan PermintaanBarangForm::configure menerima $schema/Schema
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

    /**
     * Logika utama untuk update record dari form Edit.
     * Logika ini akan meniru apa yang dilakukan oleh 'Action::make('setujui')'
     * dan dipicu ketika Admin/Maker mengklik 'Save Changes' di form Edit.
     */
    public static function handleRecordUpdate(Model $record, array $data): Model
    {
        $oldStatus = $record->status;
        $newStatus = $data['status'] ?? $oldStatus;
        $currentAuthId = Auth::id();

        // Cek jika status berubah dan menjadi 'dikeluarkan'
        if ($oldStatus !== 'dikeluarkan' && $newStatus === 'dikeluarkan') {
            
            try {
                DB::transaction(function () use ($record, $data, $currentAuthId) {
                    $aset = Aset::find($record->aset_id); // Ambil Aset dari record lama
                    
                    if (!$aset) {
                         throw new \Exception('Aset (ATK) tidak ditemukan.');
                    }

                    $jumlahPinjam = (int) ($data['jumlah_pinjam'] ?? $record->jumlah_pinjam); // Gunakan data form atau record lama
                    $stokSebelum = (int) $aset->jumlah_barang;

                    if ($stokSebelum < $jumlahPinjam) {
                        throw new \Exception("Jumlah barang '{$aset->nama_barang}' tidak mencukupi. Sisa stok: {$stokSebelum}");
                    }
                    
                    $peminjamNama = $record->user?->name ?? 'Pemohon';
                    $lokasiGudang = $aset->lokasi; 
                    $stokSesudah = $stokSebelum - $jumlahPinjam;
                    
                    // 1. Update STOK Aset dengan optimistic locking seperti action table
                    $updated = DB::table('asets')
                        ->where('id', $aset->id)
                        ->where('jumlah_barang', $stokSebelum)
                        ->update(['jumlah_barang' => $stokSesudah]);

                    if (!$updated) {
                        throw new \Exception('Terjadi masalah konsistensi data stok (Race Condition). Silakan coba lagi.');
                    }
                    
                    // Tambahkan data approval ke $data sebelum update
                    $data['tanggal_approval'] = Carbon::now()->setTimezone(config('app.timezone'));
                    $data['admin_id'] = $currentAuthId;
                    $data['jumlah_dikembalikan'] = $jumlahPinjam;
                    $data['lokasi_sebelum'] = $lokasiGudang; 

                    // 2. Update record pengajuan dengan data yang sudah dimodifikasi
                    $record->update($data);
                    
                    // 3. Catat riwayat PENGELUARAN BARANG ATK
                    RiwayatAset::create([
                        'aset_id' => $aset->id,
                        'user_id' => $currentAuthId,
                        'tipe' => 'permintaan_atk_dikeluarkan', 
                        'jumlah_perubahan' => -$jumlahPinjam,
                        'stok_sebelum' => $stokSebelum,
                        'stok_sesudah' => $stokSesudah,
                        'lokasi_sebelum' => $lokasiGudang, 
                        'lokasi_sesudah' => $peminjamNama . ' (Diterima)', 
                        'keterangan' => 'Permintaan ATK disetujui dan dikeluarkan melalui Edit Manual oleh ' . (auth()->user()->name ?? 'Admin') . '.',
                    ]);

                    Notification::make()
                        ->title('Permintaan ATK dikeluarkan')
                        ->body("Permintaan {$aset->nama_barang} telah disetujui dan barang dikeluarkan. Stok tersisa: {$stokSesudah}")
                        ->success()
                        ->send();
                });

                return $record; // Kembalikan record yang sudah di-update

            } catch (\Throwable $e) {
                // Tampilkan error dan batalkan update form
                Notification::make()->title('Gagal Pengeluaran')->body($e->getMessage())->danger()->send();
                
                // Simpan record lama tanpa perubahan status baru, dan hentikan proses.
                return $record; 
            }
        } 
        
        // Cek jika status berubah dan menjadi 'diverifikasi' atau 'ditolak'
        if (($oldStatus !== 'diverifikasi' && $newStatus === 'diverifikasi') || ($oldStatus !== 'ditolak' && $newStatus === 'ditolak')) {
             // Jika diverifikasi/ditolak (tidak ada pengurangan stok)
            $data['tanggal_approval'] = Carbon::now()->setTimezone(config('app.timezone'));
            $data['admin_id'] = $currentAuthId;

            $record->update($data);

            Notification::make()
                ->title('Status Diperbarui')
                ->body("Status permintaan diubah menjadi " . ucfirst($newStatus) . ".")
                ->success()
                ->send();

            return $record;
        }

        // Jika tidak ada perubahan status kritis, lakukan update normal
        $record->update($data);
        return $record;
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
        if (!Auth::user()->hasAnyRole(['maker', 'approver'])) {
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
        return $user->can('view pengajuan') && ($user->hasAnyRole(['maker', 'approver']) || $record->user_id === $user->id);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create pengajuan');
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        $isMaker = $user->hasRole('maker');
        $isApprover = $user->hasRole('approver');
        $hasAdminRole = $isMaker || $isApprover;

        // Jika status sudah 'dikeluarkan', TIDAK ADA YANG BOLEH EDIT (termasuk Admin).
        if ($record->status === 'dikeluarkan') {
            return false;
        }

        // 1. Maker: boleh edit jika
        //    - status 'diajukan' atau 'diverifikasi', atau
        //    - status 'ditolak' dan penolaknya adalah maker sendiri (admin_id == user)
        if ($isMaker) {
            if (in_array($record->status, ['diajukan', 'diverifikasi'])) {
                return $user->can('edit pengajuan');
            }
            if ($record->status === 'ditolak') {
                return $record->admin_id === $user->id && $user->can('edit pengajuan');
            }
            return false;
        }

        // 2. Approver:
        //    - Boleh edit saat status 'diverifikasi' (untuk mengeluarkan/menolak)
        //    - Jika status 'ditolak', hanya boleh edit jika dia sendiri yang menolak (admin_id == user)
        if ($isApprover) {
            if ($record->status === 'diverifikasi') {
                return $user->can('edit pengajuan');
            }
            if ($record->status === 'ditolak') {
                return $record->admin_id === $user->id && $user->can('edit pengajuan');
            }
            // Approver juga boleh mengedit pengajuan MILIKNYA saat status 'diajukan'
            if ($record->status === 'diajukan' && $record->user_id === $user->id) {
                return $user->can('edit pengajuan');
            }
            return false;
        }

        // 3. User biasa (non-admin) hanya diizinkan jika:
        //    a. Dia adalah pemilik record, DAN
        //    b. Statusnya HANYA 'diajukan'.
        $isUserAllowed = $record->user_id === $user->id && $record->status === 'diajukan';

        return $user->can('edit pengajuan') && $isUserAllowed;
    }
    
    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();
        // Hanya pemilik yang boleh menghapus dan hanya saat status 'diajukan'
        return $user->id === $record->user_id && $record->status === 'diajukan' && $user->can('delete pengajuan');
    }

    /**
     * Konfigurasi global search untuk mencari di kolom yang relevan
     */
    public static function getGloballySearchableAttributes(): array
    {
        return [
            'id',
            'user.name',
            'aset.nama_barang',
            'status',
        ];
    }

    /**
     * Judul yang ditampilkan di hasil global search
     */
    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "Permintaan ATK #{$record->id} - {$record->aset->nama_barang} ({$record->user->name})";
    }

    /**
     * Detail tambahan yang ditampilkan di hasil global search
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Status' => ucfirst($record->status),
            'Jumlah' => $record->jumlah_pinjam,
            'Tanggal' => $record->created_at->format('d/m/Y'),
        ];
    }

    /**
     * Modifikasi query global search untuk memastikan relasi dimuat
     */
    public static function modifyGlobalSearchQuery(Builder $query, string $search): void
    {
        $query->with(['user', 'aset']);
    }

    /**
     * Memastikan global search aktif
     */
    public static function canGloballySearch(): bool
    {
        return true;
    }

    /**
     * Query global search dengan filter yang benar
     */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return static::getEloquentQuery();
    }

    /**
     * URL hasil global search
     */
    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        $canEdit = static::canEdit($record);
        
        if ($canEdit) {
            return static::getUrl('edit', ['record' => $record]);
        }
        
        return null;
    }

    /**
     * Aksi yang tersedia di hasil global search
     */
    public static function getGlobalSearchResultActions(Model $record): array
    {
        return [];
    }

    /**
     * Batas jumlah hasil global search
     */
    public static function getGlobalSearchResultsLimit(): int
    {
        return 50;
    }

    /**
     * Pencarian tidak case sensitive
     */
    public static function isGlobalSearchForcedCaseInsensitive(): ?bool
    {
        return true;
    }

    /**
     * Pencarian bisa memisahkan kata kunci
     */
    public static function shouldSplitGlobalSearchTerms(): bool
    {
        return true;
    }

    /**
     * Hasil global search
     */
    public static function getGlobalSearchResults(string $search): \Illuminate\Support\Collection
    {
        $query = static::getGlobalSearchEloquentQuery();
        
        static::applyGlobalSearchAttributeConstraints($query, $search);
        
        static::modifyGlobalSearchQuery($query, $search);
        
        return $query
            ->limit(static::getGlobalSearchResultsLimit())
            ->get()
            ->map(function (Model $record): ?\Filament\GlobalSearch\GlobalSearchResult {
                $url = static::getGlobalSearchResultUrl($record);
                
                if (blank($url)) {
                    return null;
                }
                
                return new \Filament\GlobalSearch\GlobalSearchResult(
                    title: static::getGlobalSearchResultTitle($record),
                    url: $url,
                    details: static::getGlobalSearchResultDetails($record),
                    actions: array_map(
                        fn (\Filament\Actions\Action $action) => $action->hasRecord() ? $action : $action->record($record),
                        static::getGlobalSearchResultActions($record),
                    ),
                );
            })
            ->filter();
    }

    /**
     * Konstrain pencarian global
     */
    protected static function applyGlobalSearchAttributeConstraints(Builder $query, string $search): void
    {
        $search = \Filament\Support\generate_search_term_expression($search, static::isGlobalSearchForcedCaseInsensitive(), $query->getConnection());

        if (! static::shouldSplitGlobalSearchTerms()) {
            $isFirst = true;

            foreach (static::getGloballySearchableAttributes() as $attributes) {
                static::applyGlobalSearchAttributeConstraint(
                    query: $query,
                    search: $search,
                    searchAttributes: \Illuminate\Support\Arr::wrap($attributes),
                    isFirst: $isFirst,
                );
            }

            return;
        }

        $searchWords = array_filter(
            str_getcsv(preg_replace('/\s+/', ' ', $search), separator: ' ', escape: '\\'),
            fn ($word): bool => filled($word),
        );

        foreach ($searchWords as $searchWord) {
            $query->where(function (Builder $query) use ($searchWord): void {
                $isFirst = true;

                foreach (static::getGloballySearchableAttributes() as $attributes) {
                    static::applyGlobalSearchAttributeConstraint(
                        query: $query,
                        search: $searchWord,
                        searchAttributes: \Illuminate\Support\Arr::wrap($attributes),
                        isFirst: $isFirst,
                    );
                }
            });
        }
    }

    /**
     * Konstrain atribut pencarian global
     */
    protected static function applyGlobalSearchAttributeConstraint(Builder $query, string $search, array $searchAttributes, bool &$isFirst): Builder
    {
        $isForcedCaseInsensitive = static::isGlobalSearchForcedCaseInsensitive();
        $databaseConnection = $query->getConnection();

        foreach ($searchAttributes as $searchAttribute) {
            $whereClause = $isFirst ? 'where' : 'orWhere';

            $query->when(
                str($searchAttribute)->contains('.'),
                function (Builder $query) use ($databaseConnection, $isForcedCaseInsensitive, $searchAttribute, $search, $whereClause): Builder {
                    return $query->{"{$whereClause}Has"}(
                        (string) str($searchAttribute)->beforeLast('.'),
                        fn (Builder $query) => $query->where(
                            \Filament\Support\generate_search_column_expression($query->qualifyColumn((string) str($searchAttribute)->afterLast('.')), $isForcedCaseInsensitive, $databaseConnection),
                            'like',
                            "%{$search}%",
                        ),
                    );
                },
                fn (Builder $query) => $query->{$whereClause}(
                    \Filament\Support\generate_search_column_expression($query->qualifyColumn($searchAttribute), $isForcedCaseInsensitive, $databaseConnection),
                    'like',
                    "%{$search}%",
                ),
            );

            $isFirst = false;
        }

        return $query;
    }
}
