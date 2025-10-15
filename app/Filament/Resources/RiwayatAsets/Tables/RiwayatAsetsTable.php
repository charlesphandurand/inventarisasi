<?php

namespace App\Filament\Resources\RiwayatAsets\Tables;

// --- Filament Core Actions ---
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Actions\BulkAction; 

// --- Filament Table Components ---
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

// --- Filament Form Components ---
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\CheckboxList;

// --- Laravel & Database ---
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;
use Illuminate\Support\Str; // Tambahkan ini jika belum ada (Anda sudah menggunakannya di Blade)

// --- Export Libraries (pxlrbt/FilamentExcel) ---
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

        // --- DEFINISI KOLOM EKSPOR UNTUK CUSTOM SELECTOR ---
        $exportColumns = [
            'aset.nama_barang' => 'Nama Aset',
            'created_at' => 'Tanggal/Waktu',
            'tipe' => 'Tipe Transaksi',
            'lokasi_sebelum' => 'Lokasi Sebelum',
            'lokasi_sesudah' => 'Lokasi Sesudah',
            'harga_sebelum' => 'Harga Sebelum',
            'harga_sesudah' => 'Harga Sesudah',
            'jumlah_perubahan' => 'Perubahan Stok',
            'stok_sebelum' => 'Stok Sebelum',
            'stok_sesudah' => 'Stok Sesudah',
            'user.name' => 'Penanggung Jawab',
            'keterangan' => 'Keterangan',
        ];

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

        // Fungsi Helper untuk format Rupiah (Excel/View)
        $formatRupiahExcel = function ($state) {
            if (is_numeric($state)) {
                return 'Rp ' . number_format((float)$state, 0, ',', '.');
            }
            return 'Rp 0';
        };

        // **PERBAIKAN UTAMA DI SINI:** Fungsi Helper untuk format Rupiah (PDF View), mengembalikan '-' jika 0
        $formatRupiahView = function ($value) {
            $value = (float) $value;
            // Jika nilai adalah 0 atau null, kembalikan '-'
            if ($value == 0 || is_null($value)) { 
                return '-';
            }
            // Jika ada nilai, format sebagai Rupiah
            return 'Rp ' . number_format($value, 0, ',', '.');
        };
        
        // Fungsi Helper untuk memformat Keterangan (Full Text)
        $formatKeterangan = function ($value) {
             return $value ?? '-'; 
        };

        // --- CUSTOM FORM SCHEMA UNTUK EXPORT ---
        $exportFormSchemaBulk = [
            CheckboxList::make('selected_columns')
                ->label('Pilih Kolom yang Akan Diekspor')
                ->options($exportColumns)
                ->default(array_keys($exportColumns))
                ->columns(3) 
                ->required(),
        ];
        // --- AKHIR SKEMA FORM ---

        return $table
            ->heading('Riwayat Transaksi Aset')
            ->columns([
                TextColumn::make('aset.nama_barang')->label('Aset')->searchable()->sortable(),
                TextColumn::make('aset.nama_vendor')->label('Vendor')->searchable()->sortable(),
                TextColumn::make('tipe')
                    ->label('Tipe Transaksi')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pinjam_dikembalikan' => 'warning',
                        'create', 'penambahan', 'permintaan_atk_dikeluarkan' => 'success',
                        'pinjam_disetujui' => 'primary',
                        'lokasi_update', 'harga_update' => 'info',
                        'pinjam_dihapus', 'pengurangan' => 'danger',
                        default => 'gray',
                    })
                    ->sortable()
                    ->formatStateUsing($formatTipe),
                TextColumn::make('jumlah_perubahan')->label('Perubahan Stok')->numeric(),
                TextColumn::make('stok_sebelum')->label('Stok Sebelum')->numeric(),
                TextColumn::make('stok_sesudah')->label('Stok Sesudah')->numeric(),
                TextColumn::make('harga_sebelum')
                    ->label('Harga Sebelum')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('harga_sesudah')
                    ->label('Harga Sesudah')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('lokasi_sebelum')->label('Lokasi Sebelumnya')->formatStateUsing(fn ($state) => $state ?? '-'),
                TextColumn::make('lokasi_sesudah')->label('Lokasi Sesudah')->formatStateUsing(fn ($state) => $state ?? '-'),
                TextColumn::make('user.name')->label('Diubah Oleh')->searchable()->sortable(),
                TextColumn::make('created_at')->label('Waktu')->dateTime()->sortable(),
                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(35)
                    ->tooltip(fn ($state): ?string => $state)
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
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
                BulkActionGroup::make([
                    // 1. Bulk Action Export Excel/CSV (Custom Column)
                    BulkAction::make('export_riwayat_excel_bulk')
                        ->label('Ekspor XLSX/CSV (Pilih Kolom)')
                        ->color('success')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->visible(fn () => $isAdminOrApprover)
                        ->form($exportFormSchemaBulk) 
                        ->action(function (Collection $records, array $data) use ($formatTipe, $formatRupiahExcel, $exportColumns) { 
                            if ($records->isEmpty()) {
                                Notification::make()->warning()->title('Pilih Data')->body('Harap pilih minimal satu riwayat aset untuk diekspor.')->send();
                                return;
                            }

                            $selectedColumns = $data['selected_columns'];
                            $excelColumns = [];

                            foreach ($selectedColumns as $columnKey) {
                                $column = Column::make($columnKey)->heading($exportColumns[$columnKey]);

                                if ($columnKey === 'tipe') {
                                    $column->formatStateUsing($formatTipe);
                                } elseif (in_array($columnKey, ['harga_sebelum', 'harga_sesudah'])) {
                                    $column->formatStateUsing($formatRupiahExcel); // Menggunakan format Rupiah untuk Excel
                                }
                                
                                $excelColumns[] = $column;
                            }

                            $export = ExcelExport::make('riwayat_aset_bulk_export')
                                ->withFilename('Riwayat_Aset_Bulk_' . now()->format('Ymd_His'))
                                ->withColumns($excelColumns)
                                ->fromCollection($records->load(['aset', 'user'])); 

                            return $export->download();
                        })
                        ->deselectRecordsAfterCompletion(),
                    
                    // 2. Bulk Action Export PDF Tabular (Custom Column)
                    BulkAction::make('export_riwayat_pdf_bulk')
                        ->label('Ekspor PDF (Pilih Kolom)')
                        ->color('danger')
                        ->icon('heroicon-o-document-arrow-down')
                        ->visible(fn () => $isAdminOrApprover)
                        ->form($exportFormSchemaBulk) 
                        ->action(function (Collection $records, array $data) use ($formatTipe, $exportColumns, $formatRupiahView, $formatKeterangan) {
                            if ($records->isEmpty()) {
                                Notification::make()->warning()->title('Pilih Data')->body('Harap pilih minimal satu riwayat aset untuk diekspor.')->send();
                                return;
                            }

                            try {
                                $records = $records->load(['aset', 'user']); // Load relasi
                                $filename = 'Riwayat_Aset_Bulk_' . now()->format('Ymd_His') . '.pdf';
                                // Menggunakan sys_get_temp_dir() untuk lokasi sementara yang aman
                                $path = sys_get_temp_dir() . '/' . $filename; 
                                
                                $selectedHeaders = collect($data['selected_columns'])->mapWithKeys(fn ($key) => [$key => $exportColumns[$key]])->toArray();

                                // Panggil Spatie PDF dengan data dan helper functions
                                Pdf::view('exports.riwayat_aset_laporan_pdf', [
                                    'data' => $records,
                                    'title' => 'Laporan Riwayat Transaksi Aset Terpilih',
                                    // Mengirimkan helper functions
                                    'formatTipe' => $formatTipe,
                                    'formatRupiah' => $formatRupiahView, // **MENGGUNAKAN FUNGSI YANG BARU DIFIX**
                                    'formatKeterangan' => $formatKeterangan, // Mengirimkan format Keterangan
                                    'selectedHeaders' => $selectedHeaders, 
                                    'dateFrom' => 'Data Terpilih', 
                                    'dateTo' => 'Data Terpilih',
                                ])
                                    ->format(Format::A4)
                                    ->landscape()
                                    ->save($path);

                                return Response::download($path, $filename)->deleteFileAfterSend(true);

                            } catch (\Exception $e) {
                                Log::error('Riwayat PDF Bulk Export Error: ' . $e->getMessage(), ['exception' => $e]);
                                Notification::make()
                                    ->danger()
                                    ->title('Gagal mengekspor PDF')
                                    ->body('Error: ' . $e->getMessage() . '. Pastikan semua dependensi PDF terinstal dan view `exports.riwayat_aset_laporan_pdf` tersedia. Cek juga apakah ada kode Blade yang menyebabkan error saat rendering.')
                                    ->persistent()
                                    ->send();
                                return null;
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}