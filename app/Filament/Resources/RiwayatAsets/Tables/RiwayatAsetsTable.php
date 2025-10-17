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
use Filament\Forms\Components\DatePicker; // PASTIKAN INI ADA
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Fieldset; // TIDAK DIUBAH SESUAI PERMINTAAN

// --- Laravel & Database ---
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;
use Illuminate\Support\Str; 
use Illuminate\Support\Facades\App; 

// --- MAATWEBSITE/EXCEL IMPORTS ---
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelFileType; 
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;

// --- IMPORTS UNTUK SPATIE PDF ---
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\Enums\Format;

class RiwayatAsetsTable
{
    public static function configure(Table $table): Table
    {
        // Pengecekan Izin
        $isMakerOrApprover = Auth::user() && Auth::user()->hasAnyRole(['maker', 'approver']);

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
        
        // --- HELPER UNTUK FORMAT RUPIAH (0 atau null menjadi '-') ---

        // Fungsi Helper untuk format Rupiah (PDF/Web/Export)
        $formatRupiahView = function ($value, $includePrefix = true) {
            $value = (float) $value;
            // Harga 0 atau NULL ditampilkan strip karena tidak relevan
            if ($value == 0 || is_null($value)) { 
                return '-'; 
            }
            $formatted = number_format($value, 0, ',', '.');
            return $includePrefix ? 'Rp ' . $formatted : $formatted;
        };

        // Fungsi Helper untuk format Stok (HANYA ANGKA, 0 atau null menjadi '0')
        $formatStokView = function ($value) {
            if (is_null($value) || !is_numeric($value)) { 
                return '0';
            }
            return (string) (int) $value;
        };
        
        // Fungsi Helper untuk memformat Keterangan (Full Text)
        $formatKeterangan = function ($value) {
            return $value ?? '-'; 
        };

        // --- CUSTOM FORM SCHEMA UNTUK EXPORT TERPADU ---
        $exportFormSchemaTerpadu = [
            // PENAMBAHAN FIELD RENTANG TANGGAL
            Fieldset::make('Filter Rentang Waktu (Optional)')
                ->schema([
                    DatePicker::make('date_from')
                        ->label('Dari Tanggal')
                        ->maxDate(now()) // Tidak bisa memilih tanggal di masa depan
                        ->placeholder(now()->startOfMonth()->format('Y-m-d')),
                    
                    DatePicker::make('date_to')
                        ->label('Sampai Tanggal')
                        ->maxDate(now())
                        ->placeholder(now()->format('Y-m-d')),
                ])
                ->columns(2),

            // 1. SELECT FORMAT FILE TERPADU (Termasuk PDF)
            Select::make('export_format')
                ->label('Pilih Format File')
                ->options([
                    'xlsx' => 'Excel (.xlsx) - Default',
                    'xls' => 'Excel (.xls)',
                    'csv' => 'CSV (.csv)',
                    'pdf' => 'PDF (.pdf)', // Opsi PDF
                ])
                ->default('xlsx')
                ->required(),

            // 2. CHECKBOX LIST KOLOM
            CheckboxList::make('selected_columns')
                ->label('Pilih Kolom yang Akan Diekspor')
                ->options($exportColumns)
                ->default(array_keys($exportColumns))
                ->columns(3) 
                ->required(),
        ];
        // --- AKHIR SKEMA FORM TERPADU ---

        return $table
            ->heading('Riwayat Transaksi Aset')
            ->columns([
                TextColumn::make('aset.nama_barang')->label('Aset')->searchable(), // ->sortable() dihapus
                TextColumn::make('aset.nama_vendor')->label('Vendor')->searchable(), // ->sortable() dihapus
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
                    // ->sortable() dihapus
                    ->formatStateUsing($formatTipe),
                
                TextColumn::make('jumlah_perubahan')
                    ->label('Perubahan Stok')
                    ->default(0) 
                    ->alignCenter(),

                TextColumn::make('stok_sebelum')
                    ->label('Stok Sebelum')
                    ->default(0) // Nilai NULL akan ditampilkan sebagai '0'
                    ->alignCenter(),

                TextColumn::make('stok_sesudah')
                    ->label('Stok Sesudah')
                    ->default(0) // Nilai NULL akan ditampilkan sebagai '0'
                    ->alignCenter(),
                
                TextColumn::make('harga_sebelum')
                    ->label('Harga Sebelum')
                    ->formatStateUsing(fn ($state) => $formatRupiahView($state))
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                TextColumn::make('harga_sesudah')
                    ->label('Harga Sesudah')
                    ->formatStateUsing(fn ($state) => $formatRupiahView($state))
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('lokasi_sebelum')->label('Lokasi Sebelumnya')->formatStateUsing(fn ($state) => $state ?? '-'),
                TextColumn::make('lokasi_sesudah')->label('Lokasi Sesudah')->formatStateUsing(fn ($state) => $state ?? '-'),
                
                TextColumn::make('user.name')->label('Diubah Oleh')->searchable()->formatStateUsing(fn ($state) => $state ?? '-'), // ->sortable() dihapus
                TextColumn::make('created_at')->label('Waktu')->dateTime(), // ->sortable() dihapus
                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(35)
                    ->tooltip(fn ($state): ?string => $state)
                    ->formatStateUsing(fn ($state) => $state ?? '-')
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            // DIUBAH: Default sorting sekarang 'desc' (Terlama ke Terbaru)
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
                    // --- BULK ACTION EKSPOR TERPADU (XLSX, XLS, CSV, PDF) ---
                    BulkAction::make('export_riwayat_terpadu')
                        ->label('Ekspor Data Terpilih (Pilih Format)')
                        ->color('primary') // Warna netral
                        ->icon('heroicon-o-arrow-down-tray')
                        ->form($exportFormSchemaTerpadu) // Menggunakan skema form terpadu
                        ->action(function (Collection $records, array $data) use ($formatTipe, $exportColumns, $formatStokView, $formatRupiahView, $formatKeterangan) { 
                            if ($records->isEmpty()) {
                                Notification::make()->warning()->title('Pilih Data')->body('Harap pilih minimal satu riwayat aset untuk diekspor.')->send();
                                return;
                            }
                            
                            // Ambil data filter tanggal
                            $dateFrom = $data['date_from'];
                            $dateTo = $data['date_to'];
                            
                            // Terapkan filter tanggal ke Collection $records
                            if ($dateFrom || $dateTo) {
                                $records = $records->filter(function ($record) use ($dateFrom, $dateTo) {
                                    $createdAt = Carbon::parse($record->created_at)->startOfDay();
                                    
                                    if ($dateFrom && $dateTo) {
                                        // Pastikan Tanggal Dari lebih kecil dari Tanggal Sampai (atau sama)
                                        $from = Carbon::parse($dateFrom)->startOfDay();
                                        $to = Carbon::parse($dateTo)->endOfDay();
                                        return $createdAt->between($from, $to);
                                    } elseif ($dateFrom) {
                                        $from = Carbon::parse($dateFrom)->startOfDay();
                                        return $createdAt->greaterThanOrEqualTo($from);
                                    } elseif ($dateTo) {
                                        $to = Carbon::parse($dateTo)->endOfDay();
                                        return $createdAt->lessThanOrEqualTo($to);
                                    }
                                    return true; // Jika data $records kosong atau filter tidak terdefinisi (seharusnya tidak terjadi di sini)
                                });
                            }

                            // Cek lagi setelah difilter
                            if ($records->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Tidak Ada Data yang Ditemukan')
                                    ->body('Data riwayat aset yang dipilih kosong setelah diterapkan filter rentang tanggal.')
                                    ->send();
                                return;
                            }


                            $format = $data['export_format'];
                            $selectedKeys = $data['selected_columns'];
                            $records->load(['aset', 'user']);
                            $filename = 'Riwayat_Aset_Terpilih_' . now()->format('Ymd_His');
                            
                            // Format rentang tanggal untuk judul laporan/nama file
                            $dateFromText = $dateFrom ? Carbon::parse($dateFrom)->format('d M Y') : 'Awal Data';
                            $dateToText = $dateTo ? Carbon::parse($dateTo)->format('d M Y') : 'Data Terkini';


                            // --- LOGIKA PDF ---
                            if ($format === 'pdf') {
                                try {
                                    $filename .= '.pdf';
                                    $path = sys_get_temp_dir() . '/' . $filename; 
                                    
                                    $selectedHeaders = collect($selectedKeys)->mapWithKeys(fn ($key) => [$key => $exportColumns[$key]])->toArray();

                                    // Mengirimkan semua data dan helper ke Blade
                                    Pdf::view('exports.riwayat_aset_laporan_pdf', [
                                        'data' => $records,
                                        'title' => 'Laporan Riwayat Transaksi Aset Terpilih',
                                        'formatTipe' => $formatTipe,
                                        'formatRupiah' => $formatRupiahView, 
                                        'formatStok' => $formatStokView, 
                                        'formatKeterangan' => $formatKeterangan,
                                        'selectedHeaders' => $selectedHeaders, 
                                        'dateFrom' => $dateFromText, // Tambahkan informasi rentang tanggal
                                        'dateTo' => $dateToText, // Tambahkan informasi rentang tanggal
                                    ])
                                        ->format(Format::A4)
                                        ->landscape()
                                        ->save($path);

                                    Notification::make()->success()->title('Ekspor Berhasil')->body("File PDF **{$filename}** berhasil dibuat.")->send();
                                    return Response::download($path, $filename)->deleteFileAfterSend(true);

                                } catch (\Exception $e) {
                                    Log::error('Riwayat PDF Bulk Export Error: ' . $e->getMessage(), ['exception' => $e]);
                                    Notification::make()
                                        ->danger()
                                        ->title('Gagal mengekspor PDF')
                                        ->body('Error: ' . $e->getMessage() . '. Cek log untuk detail lebih lanjut.')
                                        ->persistent()
                                        ->send();
                                    return null;
                                }
                            } 
                            
                            // --- LOGIKA EXCEL/CSV ---
                            else {
                                
                                $excelFormat = match ($format) {
                                    'csv' => ExcelFileType::CSV,
                                    'xls' => ExcelFileType::XLS,
                                    default => ExcelFileType::XLSX,
                                };

                                $filename .= '.' . $format;
                                
                                // Definisikan Anonymous Export Class menggunakan Maatwebsite/Excel
                                $export = new class($records, $selectedKeys, $exportColumns, $formatTipe, $formatStokView, $formatRupiahView) implements FromCollection, WithMapping, WithHeadings {
                                    protected Collection $records;
                                    protected array $selectedKeys;
                                    protected array $exportColumns;
                                    protected \Closure $formatTipe;
                                    protected \Closure $formatStokView;
                                    protected \Closure $formatRupiahView;
                                    
                                    public function __construct(
                                        Collection $records, 
                                        array $selectedKeys, 
                                        array $exportColumns, 
                                        \Closure $formatTipe,
                                        \Closure $formatStokView,
                                        \Closure $formatRupiahView
                                    ) {
                                        $this->records = $records;
                                        $this->selectedKeys = $selectedKeys;
                                        $this->exportColumns = $exportColumns;
                                        $this->formatTipe = $formatTipe;
                                        $this->formatStokView = $formatStokView;
                                        $this->formatRupiahView = $formatRupiahView; 
                                    }

                                    public function collection()
                                    {
                                        return $this->records;
                                    }

                                    public function headings(): array
                                    {
                                        $headings = [];
                                        foreach ($this->selectedKeys as $key) {
                                            $headings[] = $this->exportColumns[$key];
                                        }
                                        return $headings;
                                    }

                                    public function map($item): array
                                    {
                                        $mappedData = [];
                                        
                                        foreach ($this->selectedKeys as $key) {
                                            $value = data_get($item, $key);
                                            
                                            if ($key === 'tipe') {
                                                $mappedData[] = ($this->formatTipe)($value);
                                            // MODIFIKASI: MENGEMBALIKAN NILAI NUMERIK MURNI UNTUK EXCEL
                                            } elseif (in_array($key, ['harga_sebelum', 'harga_sesudah'])) {
                                                // Mengembalikan nilai float murni, yang akan dikenali Excel sebagai Number.
                                                // Jika nilainya 0 atau null, dikembalikan 0, bukan strip.
                                                $mappedData[] = is_numeric($value) ? (float) $value : 0; 
                                            // LOGIKA STOK (Excel/CSV): Mengembalikan nilai dari $formatStokView
                                            } elseif (in_array($key, ['stok_sebelum', 'stok_sesudah', 'jumlah_perubahan'])) {
                                                // Menggunakan $formatStokView yang mengembalikan string angka atau '0'
                                                $mappedData[] = ($this->formatStokView)($value); 
                                            } elseif ($key === 'created_at') {
                                                $mappedData[] = $value ? Carbon::parse($value)->format('Y-m-d H:i:s') : '-';
                                            } else {
                                                $mappedData[] = $value ?? '-';
                                            }
                                        }
                                        
                                        return $mappedData;
                                    }
                                };
                                
                                Notification::make()->success()->title('Ekspor Berhasil')->body("File {$format} **{$filename}** berhasil dibuat.")->send();
                                return Excel::download($export, $filename, $excelFormat); 
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    
                ]),
            ]);
    }
}