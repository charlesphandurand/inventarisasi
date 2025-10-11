<?php

namespace App\Filament\Widgets;

use App\Models\Aset;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
// Menggunakan impor yang benar dari pxlrbt/filament-excel untuk direct download
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction; // Import pxlrbt Action
use pxlrbt\FilamentExcel\Exports\ExcelExport; // Import pxlrbt Exporter
use pxlrbt\FilamentExcel\Columns\Column; // Import pxlrbt Column
// use App\Exports\CustomLowStockExport; // TIDAK DIGUNAKAN LAGI

class LowStockWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        $user = Auth::user();
        
        // 1. Filter dasar: Stok rendah (<= 5)
        $query = Aset::where('jumlah_barang', '<=', 5);

        // 2. Filter Wajib: Hanya tampilkan lokasi yang mengandung "ATK" (Case-Insensitive)
        // Menggunakan whereRaw dengan fungsi LOWER() untuk kompatibilitas multi-database
        $query->whereRaw('LOWER(lokasi) LIKE ?', ['%atk%']);

        // 3. Logika otorisasi (Admin/Approver vs User biasa)
        // Hanya tampilkan semua data jika pengguna adalah 'admin' atau 'approver'
        if ($user && ($user->hasRole('admin') || $user->hasRole('approver'))) {
            return $query;
        }

        // Jika bukan admin/approver, filter berdasarkan lokasi pengguna
        // Catatan: Filter ini akan dijalankan SETELAH filter 'ATK'
        return $query->where('lokasi', $user->lokasi ?? 'tidak-terdefenisi');
    }

    public function table(Table $table): Table
    {
        $user = Auth::user();
        $isAdminOrApprover = $user && ($user->hasRole('admin') || $user->hasRole('approver'));
        $query = $this->getTableQuery();
        
        return $table
            ->query($query)
            ->columns([
                TextColumn::make('nama_barang')
                    ->label('NAMA BARANG')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('jumlah_barang')
                    ->label('STOK SISA')
                    ->sortable()
                    ->badge()
                    ->color('danger'),
                TextColumn::make('lokasi')
                    ->label('LOKASI')
                    ->searchable()
                    ->sortable()
                    ->visible($isAdminOrApprover), // Hanya terlihat oleh admin/approver
            ])
            ->filters([
                SelectFilter::make('lokasi')
                    // [PERBAIKAN] Ambil lokasi unik yang mengandung "ATK" (case-insensitive)
                    // Ini memastikan opsi filter hanya menampilkan lokasi yang relevan.
                    ->options(
                        Aset::query()
                            ->select('lokasi')
                            ->distinct()
                            ->whereRaw('LOWER(lokasi) LIKE ?', ['%atk%'])
                            ->pluck('lokasi', 'lokasi')
                    )
                    ->visible($isAdminOrApprover)
            ])
            ->headerActions([
                ExportAction::make('lanjutan_custom') 
                    ->label('Lanjutan (XLSX/CSV)')
                    ->color('success')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->exports([
                        // Menggunakan pxlrbt/Filament-Excel INLINE Exporter.
                        // Ekspor ini secara otomatis akan menggunakan query yang difilter oleh getTableQuery().
                        ExcelExport::make('low_stock_export')
                            ->askForWriterType() // Tampilkan opsi format (XLSX/CSV)
                            ->fromTable()
                            ->withFilename(fn () => 'Laporan Low Stock ATK_' . now()->format('Ymd_His'))
                            // Pindahkan definisi kolom dari CustomLowStockExport.php ke sini
                            ->withColumns([
                                Column::make('nama_barang')->heading('NAMA BARANG'),
                                Column::make('jumlah_barang')->heading('STOK SISA'),
                                Column::make('lokasi')->heading('LOKASI'),
                                Column::make('satuan')->heading('SATUAN'),
                                Column::make('harga_satuan')
                                    ->heading('HARGA SATUAN')
                                    ->formatStateUsing(fn ($state) => $state ? 'Rp ' . number_format($state, 0, ',', '.') : '-'),
                                Column::make('total_nilai')
                                    ->heading('TOTAL NILAI')
                                    ->formatStateUsing(fn ($state) => $state ? 'Rp ' . number_format($state, 0, ',', '.') : '-'),
                                Column::make('kondisi')->heading('KONDISI'),
                                Column::make('keterangan')->heading('KETERANGAN'),
                                Column::make('tanggal_perolehan')->heading('TGL PEROLEHAN')
                                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : '-'),
                                Column::make('penanggung_jawab')->heading('P. JAWAB'),
                            ]),
                    ])
                    ->visible(fn () => $isAdminOrApprover),
            ])
            ->paginated(false)
            ->defaultSort('jumlah_barang', 'asc');
    }
}
