<?php

namespace App\Filament\Resources\Asets\Tables;

// --- Filament Core Actions (Menggunakan namespace unified Filament\Actions) ---
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action; // <-- Digunakan untuk Header, Record, DAN Column Actions
use Filament\Actions\ViewAction;
use Filament\Actions\BulkAction;

// --- Filament Table Components ---
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn; // <-- Tambahan untuk Icon Column
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;

// --- Laravel & Database ---
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;

// --- Aplikasi Lain ---
use App\Models\Aset;
use App\Filament\Resources\Asets\AsetResource;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

// --- QR Code Libraries (Bacon/SVG) ---
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

// --- Export Libraries ---
use pxlrbt\FilamentExcel\Actions\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;

// --- IMPORTS UNTUK SPATIE PDF ---
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\Browsershot\Browsershot;

class AsetsTable
{
    /**
     * Daftar Kolom yang tersedia untuk diekspor (digunakan oleh form BulkAction PDF QR).
     */
    protected static function getExportableColumns(): array
    {
        // Sesuaikan key (nama atribut Model) dan value (label tampilan) di sini
        return [
            'kode_aset' => 'Kode Aset',
            'nama_barang' => 'Nama Barang',
            'kondisi_barang' => 'Kondisi Aset',
            'jumlah_barang' => 'Jumlah',
            'lokasi' => 'Lokasi Penempatan',
            'harga' => 'Harga Beli',
            'expired_date' => 'Expired Date',
            'is_atk' => 'ATK/Non-ATK',
            'nama_vendor' => 'Nama Vendor',
            'created_at' => 'Tanggal Pembelian/Input',
            'keterangan' => 'Keterangan',
        ];
    }

    /**
     * Fungsi pembantu untuk membuat QR Code SVG.
     * Fungsi ini DIJAGA karena masih digunakan oleh aksi BulkAction 'export_data_with_qr_pdf'
     * melalui pemanggilan di Blade view.
     */
    public static function generateQrCodeSvg(string $data, int $size = 150): HtmlString
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size), // Menggunakan size yang fleksibel
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $svgString = $writer->writeString($data);
        return new HtmlString($svgString);
    }

    public static function configure(Table $table): Table
    {
        // Asumsi ini adalah Filament v2 atau v3
        $isAdmin = Auth::user()->hasAnyRole(['admin']);
        $isAdminOrApprover = Auth::user()->hasAnyRole(['admin', 'approver']);

        return $table
            ->heading('Manajemen Data Aset')
            ->columns([

                // KOLOM 1: Kondisi Barang
                TextColumn::make('kondisi_barang')
                    ->label('Kondisi')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Baik' => 'success',
                        'Kurang Baik' => 'warning',
                        'Rusak' => 'danger',
                        default => 'secondary',
                    })
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn ($state) => strtoupper($state)),

                // KOLOM 2: Status ATK
                TextColumn::make('is_atk')
                    ->label('ATK')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Ya' : 'Tidak')
                    ->color(fn (bool $state): string => $state ? 'success' : 'warning')
                    ->sortable(),

                // KOLOM 3: expired_date
                TextColumn::make('expired_date')
                    ->label('Expired Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('-'),

                // KOLOM QR CODE (Data)
                IconColumn::make('qr_status')
                    ->label('QR Status')
                    ->icon(fn (Aset $record): string => empty($record->qr_code) ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Aset $record): string => empty($record->qr_code) ? 'danger' : 'success')
                    ->tooltip(fn (Aset $record): string => empty($record->qr_code) ? 'QR Belum Dibuat' : 'QR Tersedia'),

                TextColumn::make('nama_barang')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn ($state) => strtoupper($state)),
                TextColumn::make('jumlah_barang')
                    ->label('Jumlah Barang')
                    ->numeric()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('lokasi')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn ($state) => strtoupper($state)),
                TextColumn::make('nama_vendor')
                    ->label('Nama Vendor')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('harga')
                    ->label('Harga')
                    ->money('IDR', locale: 'id')
                    ->sortable(),
                TextColumn::make('keterangan')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn ($state) => strtoupper($state)),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                // Export Excel
                ExportAction::make('lanjutan_custom')
                    ->label('Ekspor (XLSX/CSV)')
                    ->color('success')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->exports([
                        ExcelExport::make('aset_export')
                            ->askForWriterType()
                            ->fromTable()
                            ->withFilename(fn () => 'Laporan Aset_' . now()->format('Ymd_His'))
                            ->withColumns([
                                Column::make('is_atk')->heading('ATK')->formatStateUsing(fn (bool $state) => $state ? 'Ya' : 'Tidak'),
                                Column::make('kondisi_barang')->heading('KONDISI BARANG'),
                                Column::make('expired_date')->heading('EXPIRED DATE')->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : '-'),
                                Column::make('nama_barang')->heading('NAMA BARANG'),
                                Column::make('jumlah_barang')->heading('JUMLAH'),
                                Column::make('nama_vendor')->heading('NAMA VENDOR'),
                                Column::make('harga')->heading('HARGA'),
                                Column::make('lokasi')->heading('LOKASI'),
                                Column::make('keterangan')->heading('KETERANGAN'),
                                Column::make('created_at')->heading('TANGGAL DIBUAT'),
                            ]),
                    ])
                    ->visible(fn () => $isAdminOrApprover),

                // Export PDF (Data Tabular Saja)
                Action::make('export_pdf')
                    ->label('Ekspor PDF (ALL)')
                    ->color('danger')
                    ->icon('heroicon-o-document-arrow-down')
                    ->visible(fn () => $isAdminOrApprover)
                    ->action(function (Table $table) {
                        $query = $table->getLivewire()->getFilteredTableQuery();

                        try {
                            $records = (clone $query)->get();

                            if ($records->isEmpty()) {
                                Notification::make()->warning()->title('Tidak ada data')->body('Tidak ada data aset untuk diekspor.')->send();
                                return;
                            }

                            $filename = 'Laporan_Aset_' . now()->format('Ymd_His') . '.pdf';
                            $path = storage_path('app/public/' . $filename);

                            Pdf::view('exports.aset_laporan_pdf', [
                                'data' => $records,
                                'title' => 'Laporan Data Aset Keseluruhan',
                            ])
                            ->format(Format::A4)
                            ->landscape()
                            ->save($path);

                            return Response::download($path, $filename)->deleteFileAfterSend(true);

                        } catch (\Exception $e) {
                            Log::error('PDF Export Error: ' . $e->getMessage(), ['exception' => $e]);
                            Notification::make()
                                ->danger()
                                ->title('Gagal mengekspor PDF')
                                ->body('Error: ' . $e->getMessage() . '. Pastikan semua dependensi PDF terinstal.')
                                ->persistent()
                                ->send();
                            return null;
                        }
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                // PERBAIKAN: Mengganti $isAdmin dengan $isAdminOrApprover
                // Ini akan memungkinkan pengguna dengan peran 'admin' ATAU 'approver' untuk melihat dan menggunakan tombol Edit.
                EditAction::make()->visible(fn () => $isAdminOrApprover), 

                // Aksi Cetak QR Satuan dan tampilkan modal QR Dihapus!

            ])
            ->filters([
                // Filter 1: Kondisi Barang
                SelectFilter::make('kondisi_barang')
                    ->label('Filter Kondisi')
                    ->options([
                        'Baik' => 'Baik',
                        'Kurang Baik' => 'Kurang Baik',
                        'Rusak' => 'Rusak',
                    ]),

                // Filter 2: Status ATK
                SelectFilter::make('is_atk')
                    ->label('Filter ATK')
                    ->options([
                        true => 'Ya (ATK)',
                        false => 'Bukan (Non-ATK)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (isset($data['value']) && in_array($data['value'], ['1', '0'])) {
                            $isAtk = $data['value'] === '1';
                            return $query->where('is_atk', $isAtk);
                        }
                        return $query;
                    }),

                // Filter 3: Lokasi
                Filter::make('lokasi')
                    ->label('Lokasi')
                    ->form([
                        \Filament\Forms\Components\Select::make('lokasi')
                            ->options(fn () => Aset::query()
                                ->whereNotNull('lokasi')
                                ->distinct()
                                ->pluck('lokasi', 'lokasi')
                                ->toArray())
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return isset($data['lokasi']) && $data['lokasi'] !== null
                            ? $query->where('lokasi', $data['lokasi'])
                            : $query;
                    }),

                // Filter 4: Nama Vendor
                Filter::make('nama_vendor')
                    ->label('Nama Vendor')
                    ->form([
                        \Filament\Forms\Components\Select::make('nama_vendor')
                            ->options(fn () => Aset::query()
                                ->whereNotNull('nama_vendor')
                                ->distinct()
                                ->pluck('nama_vendor', 'nama_vendor')
                                ->toArray())
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return isset($data['nama_vendor']) && $data['nama_vendor'] !== null
                            ? $query->where('nama_vendor', $data['nama_vendor'])
                            : $query;
                    }),
            ])
            ->bulkActions([
                // Membungkus Bulk Actions agar tombolnya terlihat
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    // NEW: Aksi Ekspor PDF (Data + QR Code) - Dijaga karena ini yang fungsional
                    BulkAction::make('export_data_with_qr_pdf')
                        ->label('Ekspor PDF (Data + QR Code)')
                        ->icon('heroicon-o-document-check')
                        ->color('success') // Ganti warna agar beda dengan tabular PDF
                        ->action(function (Collection $records) {
                            if ($records->isEmpty()) {
                                Notification::make()->warning()->title('Pilih Aset')->body('Harap pilih minimal satu aset untuk diekspor.')->send();
                                return;
                            }

                            // Hapus record yang tidak memiliki QR Code
                            $records = $records->filter(fn ($record) => !empty($record->qr_code));

                            if ($records->isEmpty()) {
                                Notification::make()->warning()->title('Gagal Ekspor')->body('Semua aset yang dipilih tidak memiliki QR Code yang tersedia.')->send();
                                return;
                            }

                            $filename = 'Laporan_Aset_QR_Batch_' . now()->format('Ymd_His') . '.pdf';
                            $path = storage_path('app/public/' . $filename);

                            try {
                                Pdf::view('exports.aset_qr_batch_pdf', [ // MENGGUNAKAN VIEW BARU
                                    'data' => $records,
                                    'title' => 'Laporan Aset Terpilih dengan QR Code',
                                    // Pass callable function untuk digunakan di Blade
                                    'generateQrCodeSvg' => [self::class, 'generateQrCodeSvg'],
                                    'AsetResource' => AsetResource::class,
                                ])
                                ->format(Format::A4)
                                ->landscape()
                                ->save($path);

                                return Response::download($path, $filename)->deleteFileAfterSend(true);

                            } catch (\Exception $e) {
                                Log::error('PDF QR Export Error: ' . $e->getMessage(), ['exception' => $e]);
                                Notification::make()
                                    ->danger()
                                    ->title('Gagal mengekspor PDF QR')
                                    ->body('Terjadi kesalahan saat membuat PDF: ' . $e->getMessage())
                                    ->persistent()
                                    ->send();
                                return null;
                            }
                        })
                        ->visible(fn () => $isAdminOrApprover) // Batasi juga BulkAction ini
                        ->deselectRecordsAfterCompletion(),
                ])
            ]);
    }
}
