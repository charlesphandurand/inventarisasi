<?php

namespace App\Filament\Resources\Asets\Tables;

// --- Filament Core Actions (Menggunakan namespace unified Filament\Actions) ---
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action; // Digunakan untuk Header, Record, DAN Column Actions
use Filament\Actions\ViewAction;
use Filament\Actions\BulkAction;

// --- Filament Table Components ---
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn; 
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;

// --- Filament Form Components (BARU: WAJIB UNTUK TOGGLE DAN SELECT) ---
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;

// --- Filament Form Utilities (PENTING: Memperbaiki error Set/Get) ---
use Filament\Schemas\Components\Utilities\Set; 
use Filament\Schemas\Components\Utilities\Get; 

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

// --- SPATIE PDF IMPORTS (PASTIKAN SUDAH TERINSTAL) ---
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\Enums\Format;

class AsetsTable
{
    /**
     * Daftar Kolom yang tersedia untuk diekspor (digunakan oleh form BulkAction PDF QR).
     */
    protected static function getExportableColumns(): array
    {
        // Sesuaikan key (nama atribut Model) dan value (label tampilan) di sini
        return [
            'nama_barang' => 'Nama Barang',
            'id' => 'Kode Aset',
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
        $isMakerOrApprover = Auth::user()->hasAnyRole(['maker', 'approver']);

        return $table
            ->heading('Manajemen Data Aset')
            ->columns([
                // ... Kolom-kolom lainnya (Pastikan 'id' ada di sini jika diperlukan di tabel) ...
                TextColumn::make('id')
                    ->label('Kode Aset')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => strtoupper($state)),
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

                TextColumn::make('is_atk')
                    ->label('ATK')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Ya' : 'Tidak')
                    ->color(fn (bool $state): string => $state ? 'success' : 'warning')
                    ->sortable(),

                TextColumn::make('expired_date')
                    ->label('Expired Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('-'),

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
                // ... Header Actions lainnya (Export Excel & Export PDF ALL) ...
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
                    ]),

                Action::make('export_pdf')
                    ->label('Ekspor PDF (ALL)')
                    ->color('danger')
                    ->icon('heroicon-o-document-arrow-down')
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
                EditAction::make()->visible(fn () => $isMakerOrApprover), 
            ])
            ->filters([
                // ... Filters lainnya ...
                SelectFilter::make('kondisi_barang')
                    ->label('Filter Kondisi')
                    ->options([
                        'Baik' => 'Baik',
                        'Kurang Baik' => 'Kurang Baik',
                        'Rusak' => 'Rusak',
                    ]),

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
                    // 1. DELETE BULK ACTION
                    DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->hasAnyRole(['maker', 'approver'])), 
                
                    // 2. EXPORT PDF: Sekarang dengan custom column!
                    BulkAction::make('export_data_with_qr_pdf')
                        ->label('Ekspor PDF (Data + QR Code Custom)') // Ubah label
                        ->icon('heroicon-o-document-check')
                        ->color('success') 
                        
                        // --- FORM CUSTOM COLUMN (DENGAN TOGGLE BARU) ---
                        ->form(function () {
                            // Ambil semua kunci kolom yang dapat diekspor
                            $allColumnKeys = array_keys(static::getExportableColumns());
                        
                            return [
                                // 1. TOGGLE BARU: Berfungsi sebagai tombol Pilih/Hapus Semua
                                Toggle::make('toggle_all')
                                    ->label('Pilih/Hapus Semua Kolom')
                                    ->default(true) // Default aktif (pilih semua)
                                    ->live() // Wajib agar perubahan di sini memengaruhi field 'columns'
                                    
                                    // PERBAIKAN: Mengganti \Filament\Forms\Set dengan \Filament\Schemas\Components\Utilities\Set
                                    ->afterStateUpdated(function (bool $state, Set $set) use ($allColumnKeys) {
                                        // Jika toggle ON, pilih semua kolom. Jika OFF, kosongkan.
                                        $set('columns', $state ? $allColumnKeys : []);
                                    })
                                    ->columnSpanFull(), // Agar toggle tampil di baris penuh
                                
                                // 2. SELECT KOMPONEN: Kolom yang dikontrol
                                Select::make('columns')
                                    ->label('Pilih Kolom Data yang Ingin Disertakan')
                                    ->options(static::getExportableColumns())
                                    ->multiple()
                                    ->required()
                                    ->default($allColumnKeys) // Default pilih semua
                                    ->searchable()
                                    ->live(), // Wajib agar field dapat diubah oleh 'toggle_all'
                            ];
                        })
                        
                        // --- AKHIR FORM CUSTOM COLUMN ---

                        ->action(function (Collection $records, array $data) {
                            if ($records->isEmpty()) {
                                Notification::make()->warning()->title('Pilih Aset')->body('Harap pilih minimal satu aset untuk diekspor.')->send();
                                return;
                            }
                            
                            // Hapus record yang tidak memiliki QR Code (misalnya qr_code kosong/null)
                            // Catatan: Asumsi Model Aset memiliki field `qr_code`
                            $records = $records->filter(fn ($record) => !empty($record->qr_code));
                
                            if ($records->isEmpty()) {
                                Notification::make()->warning()->title('Gagal Ekspor')->body('Semua aset yang dipilih tidak memiliki QR Code yang tersedia.')->send();
                                return;
                            }
                            
                            // Ambil kolom yang dipilih dari form data dan filter labelnya
                            $selectedColumnKeys = $data['columns'];
                            $exportableColumns = static::getExportableColumns();
                            
                            // Ambil hanya Label/Value dari kolom yang dipilih
                            $finalColumns = array_filter($exportableColumns, fn ($key) => in_array($key, $selectedColumnKeys), ARRAY_FILTER_USE_KEY);
                
                            $filename = 'Laporan_Aset_QR_Batch_' . now()->format('Ymd_His') . '.pdf';
                            $path = storage_path('app/public/' . $filename);
                
                            try {
                                // Panggil helper function di dalam closure untuk digunakan di Blade
                                $generateQrCodeSvg = function ($data, $size = 150) {
                                    return self::generateQrCodeSvg($data, $size);
                                };

                                Pdf::view('exports.aset_qr_batch_pdf', [
                                    'data' => $records,
                                    'title' => 'Laporan Aset Terpilih dengan QR Code',
                                    'selectedColumns' => $finalColumns, // <-- Pass kolom yang dipilih
                                    // Pass callable function untuk digunakan di Blade
                                    'generateQrCodeSvg' => $generateQrCodeSvg,
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
                        ->deselectRecordsAfterCompletion(),
                ])
            ]);
    }
}
