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

    protected static ?string $recordTitleAttribute = 'id';
    
    protected static ?string $navigationLabel = 'Pinjaman Barang'; 
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
        if (!Auth::user()->hasAnyRole(['maker', 'approver'])) {
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

        // Jika sudah final 'dikembalikan' tidak bisa diedit siapapun
        if ($record->status === 'dikembalikan') {
            return false;
        }

        // Maker: boleh edit saat 'diajukan', atau 'ditolak' jika yang menolak adalah maker sendiri
        if ($isMaker) {
            if ($record->status === 'diajukan') {
                return $user->can('edit pengajuan');
            }
            if ($record->status === 'ditolak') {
                return $record->admin_id === $user->id && $user->can('edit pengajuan');
            }
            return false;
        }

        // Approver: boleh edit miliknya sendiri saat 'diajukan',
        // serta 'ditolak' jika yang menolak adalah dirinya
        if ($isApprover) {
            if ($record->status === 'diajukan' && $record->user_id === $user->id) {
                return $user->can('edit pengajuan');
            }
            if ($record->status === 'ditolak') {
                return $record->admin_id === $user->id && $user->can('edit pengajuan');
            }
            return false;
        }

        // User biasa: hanya boleh edit miliknya sendiri saat 'diajukan'
        return $user->can('edit pengajuan') && ($record->user_id === $user->id && $record->status === 'diajukan');
    }
    
    public static function canDelete(Model $record): bool
    {
        // Hanya pemilik dan hanya status 'diajukan'
        return auth()->user()->id === $record->user_id && $record->status === 'diajukan' && auth()->user()->can('delete pengajuan');
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
        return "Pinjaman #{$record->id} - {$record->aset->nama_barang} ({$record->user->name})";
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