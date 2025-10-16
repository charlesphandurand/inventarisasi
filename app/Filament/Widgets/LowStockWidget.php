<?php

namespace App\Filament\Widgets;

use App\Models\Aset;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;

// Import untuk EXCEL
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction as ExcelExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport; 
use pxlrbt\FilamentExcel\Columns\Column; 

// Import untuk PDF
use Filament\Actions\Action;
use Spatie\LaravelPdf\Facades\Pdf; 
use Spatie\LaravelPdf\Enums\Format; 
use Spatie\Browsershot\Browsershot; 

class LowStockWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    // Diperbarui: Heading diperjelas bahwa filter is_atk = 1 adalah wajib
    protected static ?string $heading = 'Aset ATK Bermasalah: Stok Rendah (<=5) / Mendekati Kadaluarsa (<=30 Hari) / Rusak';
    protected int | string | array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        $user = Auth::user();
        $thirtyDaysFromNow = now()->addDays(30);

        // FILTER SANGAT WAJIB: Hanya tampilkan aset yang bertipe ATK (is_atk = 1)
        $query = Aset::query()->where('is_atk', 1);

        // Gabungkan semua kondisi 'bermasalah' yang bersifat OR: 
        // Kondisi Rusak ATAU Stok Rendah ATAU Mendekati Kadaluarsa
        $query->where(function (Builder $q) use ($thirtyDaysFromNow) {
            
            // Kondisi 1: Kondisi Rusak
            $q->where('kondisi_barang', 'rusak');

            // Kondisi 2: Stok Rendah (Stok <= 5). Filter is_atk=1 sudah diterapkan di luar.
            // Stok <= 5 hanya berlaku untuk ATK (yang sudah difilter).
            $q->orWhere('jumlah_barang', '<=', 5);

            // Kondisi 3: Mendekati Kadaluarsa (<= 30 hari)
            $q->orWhere(function (Builder $q_near_expired) use ($thirtyDaysFromNow) {
                $q_near_expired->whereNotNull('expired_date') 
                             // Harus expired sebelum atau pada 30 hari dari sekarang
                             ->whereDate('expired_date', '<=', $thirtyDaysFromNow); 
            });
        });

        // Terapkan filter lokasi user jika bukan Admin/Approver (Existing logic)
        if ($user && !($user->hasRole('admin') || $user->hasRole('approver'))) {
            $query->where('lokasi', $user->lokasi ?? 'tidak-terdefenisi');
        }

        return $query;
    }

    // Fungsi pembantu untuk menentukan alasan peringatan (digunakan di kolom tabel & ekspor)
    protected function getAlertReasons(Aset $record): array
    {
        $reasons = [];
        $today = Carbon::now()->startOfDay();
        $thirtyDaysFromNow = $today->copy()->addDays(30)->endOfDay();
        
        // Catatan: Karena query sudah memastikan is_atk = 1, kita tidak perlu mengeceknya lagi di sini.
        
        // 1. Cek Kondisi Rusak
        if (strtolower($record->kondisi_barang) === 'rusak') {
            $reasons[] = 'Kondisi Rusak';
        }

        // 2. Cek Kondisi Stok Rendah ATK (Stok <= 5)
        // Kita tahu ini ATK dari query utama, jadi hanya cek jumlah.
        if ($record->jumlah_barang <= 5) {
            $reasons[] = 'Stok Rendah ATK (' . $record->jumlah_barang . ' buah)';
        }

        // 3. Cek Kondisi Mendekati/Sudah Kadaluarsa
        if ($record->expired_date) {
            $expiredDate = Carbon::parse($record->expired_date)->startOfDay();
            
            // Selisih hari (false = return tanda negatif jika expiredDate di masa lalu)
            $daysDifference = $today->diffInDays($expiredDate, false); 

            if ($expiredDate->lte($thirtyDaysFromNow)) {
                $daysRemaining = $daysDifference;

                $statusText = $daysRemaining < 0 
                             ? "Sudah Kadaluarsa (-" . abs($daysRemaining) . " hari)" 
                             : "Mendekati Kadaluarsa (" . $daysRemaining . " hari)";
                             
                $reasons[] = $statusText;
            }
        }
        
        return array_unique($reasons);
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
                    ->visible($isAdminOrApprover),
                TextColumn::make('expired_date') 
                    ->label('TGL KADALUARSA')
                    ->date('d/m/Y')
                    ->sortable()
                    // Kolom ini wajib terlihat untuk Admin/Approver karena digunakan untuk menentukan alasan tampil
                    ->visible($isAdminOrApprover),
                TextColumn::make('kondisi_barang')
                    ->label('KONDISI')
                    ->searchable()
                    ->sortable()
                    ->visible($isAdminOrApprover)
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'baik' => 'success',
                        'rusak' => 'danger',
                        'sedang' => 'warning',
                        default => 'secondary',
                    }),
                // KOLOM PERINGATAN BARU: Urutan terakhir
                TextColumn::make('alert_reason')
                    ->label('PERINGATAN') 
                    ->getStateUsing(fn (Aset $record) => implode(', ', $this->getAlertReasons($record)))
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, 'Rusak') || str_contains($state, 'Sudah Kadaluarsa') => 'danger', 
                        str_contains($state, 'Mendekati Kadaluarsa') => 'warning',
                        str_contains($state, 'Stok Rendah') => 'info',
                        default => 'secondary',
                    }),
            ])
            ->filters([
                // Filter is_atk (Tipe Aset) dinonaktifkan karena is_atk = 1 sudah wajib di query
                // SelectFilter::make('is_atk')
                //     ->label('Tipe Aset')
                //     ->options([
                //         1 => 'ATK (Alat Tulis Kantor)',
                //         0 => 'Non-ATK',
                //     ])
                //     ->visible($isAdminOrApprover),
                SelectFilter::make('lokasi')
                    ->options(
                        Aset::query()
                            ->select('lokasi')
                            ->distinct()
                            // Tambahkan where is_atk = 1 di sini jika filter ini perlu merefleksikan hanya lokasi ATK
                            ->where('is_atk', 1) 
                            ->pluck('lokasi', 'lokasi')
                    )
                    ->visible($isAdminOrApprover),
                SelectFilter::make('kondisi_barang')
                    ->label('Kondisi Barang')
                    ->options([
                        'baik' => 'Baik',
                        'rusak' => 'Rusak',
                        'sedang' => 'Sedang',
                    ])
                    ->visible($isAdminOrApprover),
            ])
            ->headerActions([
                // ACTION EXCEL
                ExcelExportAction::make('lanjutan_custom') 
                    ->label('Ekspor (XLSX/CSV)')
                    ->color('success')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->exports([
                        ExcelExport::make('low_stock_export')
                            ->askForWriterType()
                            ->fromTable()
                            ->withFilename(fn () => 'Laporan Aset ATK Bermasalah_' . now()->format('Ymd_His'))
                            ->withColumns([
                                Column::make('nama_barang')->heading('NAMA BARANG'),
                                Column::make('jumlah_barang')->heading('STOK SISA'),
                                Column::make('lokasi')->heading('LOKASI'),
                                Column::make('expired_date') 
                                    ->heading('TGL KADALUARSA')
                                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('d/m/Y') : '-'), 
                                Column::make('kondisi_barang')->heading('KONDISI BARANG'), 
                            ]),
                    ])
                    ->visible(fn () => $isAdminOrApprover),

                // ACTION PDF (PERBAIKAN FUNGSI getAlertReasons DILEWATKAN)
                Action::make('export_pdf')
                    ->label('Ekspor PDF')
                    ->color('danger')
                    ->icon('heroicon-o-document-arrow-down')
                    ->visible(fn () => $isAdminOrApprover)
                    ->action(function () use ($query) {
                        try {
                            // Ambil data
                            $records = (clone $query)->get();
                            
                            // Validasi data
                            if ($records->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Tidak ada data')
                                    ->body('Tidak ada data aset bermasalah untuk diekspor.')
                                    ->send();
                                return;
                            }
                            
                            $filename = 'Laporan_Aset_ATK_Bermasalah_' . now()->format('Ymd_His') . '.pdf';
                            $path = storage_path('app/public/' . $filename);
                            
                            // Data yang dilewatkan ke view:
                            Pdf::view('exports.low_stock_pdf', [
                                'data' => $records,
                                'title' => 'Laporan Aset ATK Bermasalah (Stok Rendah / Mendekati Kadaluarsa <= 30 Hari / Rusak)',
                                'include_problem_reason' => true, 
                                // MELEWATKAN FUNGSI getAlertReasons
                                'getAlertReasons' => function ($record) { 
                                    return $this->getAlertReasons($record);
                                }
                            ])
                            ->landscape()
                            ->format(Format::A4)
                            ->save($path);
                            
                            return response()->download($path, $filename)->deleteFileAfterSend(true);
                            
                        } catch (\Exception $e) {
                            \Log::error('PDF Export Error: ' . $e->getMessage());
                            Notification::make()
                                ->danger()
                                ->title('Gagal mengekspor PDF')
                                ->body('Error: ' . $e->getMessage())
                                ->persistent()
                                ->send();
                            return null;
                        }
                    }),
            ])
            ->paginated(false)
            ->defaultSort('jumlah_barang', 'asc');
    }
}
