<?php

namespace App\Filament\Resources\RiwayatAsets\Tables;

// --- Filament Core Actions ---
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;

// --- Filament Table Components ---
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

// --- Laravel & Database ---
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Database\Eloquent\Collection;

// --- Export Libraries (pxlrbt/FilamentExcel) ---
use pxlrbt\FilamentExcel\Actions\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;

// --- IMPORTS UNTUK SPATIE PDF ---
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\Enums\Format;

class RiwayatAsetsTable
{
    public static function configure(Table $table): Table
    {
        // Pengecekan Izin
        $isAdminOrApprover = Auth::user() && Auth::user()->hasAnyRole(['admin', 'approver']);

        // Fungsi Helper untuk format Tipe Transaksi
        $formatTipe = fn (string $state): string => match ($state) {
            'pinjam_dikembalikan' => 'Pinjaman Dikembalikan',
            'create' => 'Aset Baru Dibuat',
            'pinjam_disetujui' => 'Pinjaman Disetujui',
            'lokasi_update' => 'Update Lokasi',
            'harga_update' => 'Update Harga',
            'pinjam_dihapus' => 'Pinjaman Ditolak/Dibatalkan',
            'penambahan' => 'Penambahan Stok',
            'pengurangan' => 'Pengurangan Stok Manual',
            'permintaan_atk_dikeluarkan' => 'ATK Dikeluarkan',
            default => ucfirst(str_replace('_', ' ', $state)),
        };

        // Fungsi Helper untuk format Rupiah (Currency/Accounting) untuk Excel (Rp X.XXX.XXX)
        $formatRupiahExcel = function ($state) {
            if (is_numeric($state)) {
                // Return string dengan format Rupiah
                return 'Rp ' . number_format((float)$state, 0, ',', '.');
            }
            return 'Rp 0';
        };

        return $table
            ->heading('Riwayat Transaksi Aset')
            // --- KOLOM TAMPILAN WEB (DIKEMBALIKAN KE KODE ASLI) ---
            ->columns([
                TextColumn::make('aset.nama_barang')
                    ->label('Aset')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('aset.nama_vendor')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tipe')
                    ->label('Tipe Transaksi')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pinjam_dikembalikan' => 'warning',
                        'create' => 'success',
                        'pinjam_disetujui' => 'primary',
                        'lokasi_update' => 'info',
                        'harga_update' => 'info',
                        'pinjam_dihapus' => 'danger',
                        'penambahan' => 'success',
                        'pengurangan' => 'danger',
                        'permintaan_atk_dikeluarkan' => 'success',
                        default => 'gray',
                    })
                    ->sortable()
                    ->formatStateUsing($formatTipe),
                TextColumn::make('jumlah_perubahan') // KOLOM INI TETAP ADA DI WEB
                    ->label('Perubahan Stok')
                    ->numeric(),
                TextColumn::make('stok_sebelum') // KOLOM INI TETAP ADA DI WEB
                    ->label('Stok Sebelum')
                    ->numeric(),
                TextColumn::make('stok_sesudah') // KOLOM INI TETAP ADA DI WEB
                    ->label('Stok Sesudah')
                    ->numeric(),
                TextColumn::make('harga_sebelum')
                    ->label('Harga Sebelum')
                    // >>> PERUBAHAN DI SINI: Menambahkan decimalPlaces: 0
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('harga_sesudah')
                    ->label('Harga Sesudah')
                    // >>> PERUBAHAN DI SINI: Menambahkan decimalPlaces: 0
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('lokasi_sebelum')
                    ->label('Lokasi Sebelumnya'),
                TextColumn::make('lokasi_sesudah')
                    ->label('Lokasi Sesudah'),
                TextColumn::make('user.name')
                    ->label('Diubah Oleh')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime()
                    ->sortable(),
                // Tambahan kolom Keterangan di web (Asumsi)
                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            // --- AKHIR KOLOM TAMPILAN WEB ---
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                // 1. Export Excel/CSV
                ExportAction::make('export_riwayat_excel')
                    ->label('Ekspor Riwayat (XLSX/CSV)')
                    ->color('success')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->exports([
                        ExcelExport::make('riwayat_aset_export')
                            ->askForWriterType()
                            // !!! PENTING: MENGHAPUS ->fromTable() UNTUK MENGHINDARI ERROR "NOT MOUNTED" !!!
                            // Ekspor hanya akan menggunakan kolom yang didefinisikan di ->withColumns
                            ->withFilename(fn () => 'Riwayat_Aset_' . now()->format('Ymd_His'))
                            ->withColumns([
                                // --- KOLOM EXCEL SESUAI DENGAN HEADER PDF YANG DIMINTA ---
                                Column::make('aset.nama_barang')->heading('Nama Aset'),
                                Column::make('created_at')->heading('Tanggal/Waktu'),
                                Column::make('tipe')->heading('Tipe')->formatStateUsing($formatTipe),
                                Column::make('lokasi_sebelum')->heading('Lokasi Sebelum'),
                                Column::make('lokasi_sesudah')->heading('Lokasi Sesudah'),
                                // Format Rupiah yang diminta (Rp X.XXX.XXX)
                                Column::make('harga_sebelum')->heading('Harga Sebelum')->formatStateUsing($formatRupiahExcel),
                                // Format Rupiah yang diminta (Rp X.XXX.XXX)
                                Column::make('harga_sesudah')->heading('Harga Sesudah')->formatStateUsing($formatRupiahExcel),
                                Column::make('user.name')->heading('Penanggung Jawab'),
                                Column::make('keterangan')->heading('Keterangan'), // Asumsi field 'keterangan' ada
                                // --- AKHIR KOLOM EXCEL ---
                            ]),
                    ])
                    ->visible(fn () => $isAdminOrApprover),

                // 2. Export PDF Tabular (Logika dipertahankan)
                Action::make('export_riwayat_pdf')
                    ->label('Ekspor Riwayat PDF')
                    ->color('danger')
                    ->icon('heroicon-o-document-arrow-down')
                    ->visible(fn () => $isAdminOrApprover)
                    ->action(function (Table $table) use ($formatTipe) {
                        $query = $table->getLivewire()->getFilteredTableQuery();

                        try {
                            $records = (clone $query)->with(['aset', 'user'])->get();

                            if ($records->isEmpty()) {
                                Notification::make()->warning()->title('Tidak ada data')->body('Tidak ada data riwayat aset untuk diekspor.')->send();
                                return;
                            }

                            $filename = 'Riwayat_Aset_' . now()->format('Ymd_His') . '.pdf';
                            $path = storage_path('app/public/' . $filename);

                            // Kirim helper format Rupiah ke view PDF
                            $formatRupiahView = fn ($value) => 'Rp ' . number_format((float)$value, 0, ',', '.');

                            // Panggil Spatie PDF dengan view custom
                            Pdf::view('exports.riwayat_aset_laporan_pdf', [
                                'data' => $records,
                                'title' => 'Laporan Riwayat Transaksi Aset',
                                'formatTipe' => $formatTipe,
                                'formatRupiah' => $formatRupiahView,
                            ])
                                ->format(Format::A4)
                                ->landscape()
                                ->save($path);

                            return Response::download($path, $filename)->deleteFileAfterSend(true);

                        } catch (\Exception $e) {
                            Log::error('Riwayat PDF Export Error: ' . $e->getMessage(), ['exception' => $e]);
                            Notification::make()
                                ->danger()
                                ->title('Gagal mengekspor PDF')
                                ->body('Error: ' . $e->getMessage() . '. Pastikan semua dependensi PDF terinstal dan view `exports.riwayat_aset_laporan_pdf` tersedia.')
                                ->persistent()
                                ->send();
                            return null;
                        }
                    }),
            ])
            ->filters([
                // Filter 1: Tipe Transaksi (Existing filter)
                SelectFilter::make('tipe')
                    ->label('Filter Tipe Transaksi')
                    ->options([
                        'create' => 'Aset Baru Dibuat',
                        'penambahan' => 'Penambahan Stok',
                        'pengurangan' => 'Pengurangan Stok Manual',
                        'pinjam_disetujui' => 'Pinjaman Disetujui',
                        'pinjam_dikembalikan' => 'Pinjaman Dikembalikan',
                        'permintaan_atk_dikeluarkan' => 'ATK Dikeluarkan',
                        'lokasi_update' => 'Update Lokasi',
                        'harga_update' => 'Update Harga',
                    ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([]),
            ]);
    }
}
