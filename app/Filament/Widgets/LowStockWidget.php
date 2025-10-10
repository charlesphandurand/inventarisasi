<?php

namespace App\Filament\Widgets;

use App\Models\Aset;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;

class LowStockWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'Aset Stok Rendah / Bermasalah di Gudang ATK';

    protected int | string | array $columnSpan = 'full';

    // Definisikan kunci pencarian lokasi
    private const LOCATION_KEYWORD = 'ATK';

    public function table(Table $table): Table
    {
        // Mendapatkan status peran pengguna saat ini
        $isAdminOrApprover = Auth::user()->hasRole('admin') || Auth::user()->hasRole('approver');

        // --- 1. QUERY UTAMA (Filter Permanen: Lokasi Mengandung "ATK") ---
        $query = Aset::query()
            // Filter lokasi yang mengandung 'ATK' (case-insensitive)
            // Ini MENGGANTIKAN filter lokasi manual (Gudang ATK Lt. 1 & 5)
            ->whereRaw('LOWER(lokasi) LIKE ?', ['%' . strtolower(self::LOCATION_KEYWORD) . '%'])
            // Terapkan filter stok rendah/bermasalah
            ->where(function ($q) {
                $q->where('jumlah_barang', '<=', 5)
                    ->orWhereRaw('LOWER(keterangan) LIKE ?', ['%rusak%'])
                    ->orWhereRaw('LOWER(keterangan) LIKE ?', ['%expired%']);
            })
            ->orderBy('jumlah_barang', 'asc')
            ->orderBy('nama_barang')
            ->limit(20);
        
        // Ambil daftar lokasi unik yang termasuk dalam filter "ATK" untuk opsi filter
        $locations = Aset::query()
            ->whereRaw('LOWER(lokasi) LIKE ?', ['%' . strtolower(self::LOCATION_KEYWORD) . '%'])
            ->pluck('lokasi', 'lokasi')
            ->toArray();


        return $table
            ->query($query)
            ->headerActions([
                Action::make('export_low_stock')
                    ->label('Unduh Low Stock (Filter ATK)')
                    ->icon('heroicon-m-arrow-down-tray')
                    // Export hanya untuk data yang mengandung keyword 'ATK'
                    ->url(fn () => route('asets.export.lowstock', ['keyword' => self::LOCATION_KEYWORD]))
                    ->openUrlInNewTab()
                    // LOGIKA PEMBATASAN VISIBILITAS
                    ->visible(fn () => $isAdminOrApprover), 
            ])
            ->columns([
                TextColumn::make('nama_barang')
                    ->label('NAMA BARANG')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),

                TextColumn::make('lokasi')
                    ->label('LOKASI')
                    ->searchable()
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),

                TextColumn::make('jumlah_barang')
                    ->label('SISA')
                    ->sortable()
                    ->color('danger')
                    ->badge(),

                TextColumn::make('keterangan')
                    ->label('KETERANGAN')
                    ->searchable()
                    ->wrap()
                    ->formatStateUsing(fn (?string $state): string => strtoupper($state ?? '-')),
            ])
            ->filters([
                // --- 2. FILTER LOKASI (HANYA INDIKATOR & PENCARIAN) ---
                SelectFilter::make('lokasi_filter')
                    ->label('Cari di Lokasi ATK')
                    // Opsi hanya lokasi yang mengandung "ATK"
                    ->options($locations) 
                    ->searchable()
                    // Karena filter sudah permanen di query utama,
                    // Filter ini diubah menjadi filter opsional yang memfilter di antara hasil ATK
                    ->query(function (Builder $query, array $data): Builder {
                        // Jika ada nilai yang dipilih pengguna, terapkan filter lokasi.
                        if (!empty($data['value'])) {
                            return $query->where('lokasi', $data['value']);
                        }
                        // Jika tidak ada nilai yang dipilih, biarkan query utama berlaku (filter ATK permanen)
                        return $query;
                    }),
            ]);
    }
}