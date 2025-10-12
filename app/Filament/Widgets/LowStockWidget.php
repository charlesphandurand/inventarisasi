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
    protected static ?string $heading = 'Aset Stok Rendah / Bermasalah di Gudang ATK';
    protected int | string | array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        $user = Auth::user();
        
        $query = Aset::where('jumlah_barang', '<=', 5);
        $query->whereRaw('LOWER(lokasi) LIKE ?', ['%atk%']);

        if ($user && ($user->hasRole('admin') || $user->hasRole('approver'))) {
            return $query;
        }

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
                    ->visible($isAdminOrApprover),
            ])
            ->filters([
                SelectFilter::make('lokasi')
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
                // ACTION EXCEL
                ExcelExportAction::make('lanjutan_custom') 
                    ->label('Ekspor (XLSX/CSV)')
                    ->color('success')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->exports([
                        ExcelExport::make('low_stock_export')
                            ->askForWriterType()
                            ->fromTable()
                            ->withFilename(fn () => 'Laporan Low Stock ATK_' . now()->format('Ymd_His'))
                            ->withColumns([
                                Column::make('nama_barang')->heading('NAMA BARANG'),
                                Column::make('jumlah_barang')->heading('STOK SISA'),
                                Column::make('lokasi')->heading('LOKASI'),
                                // Column::make('satuan')->heading('SATUAN'),
                                // Column::make('harga_satuan')
                                //     ->heading('HARGA SATUAN')
                                //     ->formatStateUsing(fn ($state) => $state ? 'Rp ' . number_format($state, 0, ',', '.') : '-'),
                                // Column::make('total_nilai')
                                //     ->heading('TOTAL NILAI')
                                //     ->formatStateUsing(fn ($state) => $state ? 'Rp ' . number_format($state, 0, ',', '.') : '-'),
                                // Column::make('kondisi')->heading('KONDISI'),
                                Column::make('keterangan')->heading('KETERANGAN'),
                                // Column::make('tanggal_perolehan')->heading('TGL PEROLEHAN')
                                //     ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : '-'),
                                // Column::make('penanggung_jawab')->heading('P. JAWAB'),
                            ]),
                    ])
                    ->visible(fn () => $isAdminOrApprover),

                // ACTION PDF (FIXED FOR LIVEWIRE)
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
                                    ->body('Tidak ada data stok rendah untuk diekspor.')
                                    ->send();
                                return;
                            }
                            
                            $filename = 'Laporan_Low_Stock_ATK_' . now()->format('Ymd_His') . '.pdf';
                            
                            // Simpan PDF ke storage/app/public sementara
                            $path = storage_path('app/public/' . $filename);
                            
                            Pdf::view('exports.low_stock_pdf', [
                                'data' => $records,
                                'title' => 'Laporan Stok Rendah ATK',
                            ])
                            ->landscape()
                            ->format(Format::A4)
                            ->save($path);
                            
                            // Download menggunakan response Laravel biasa
                            return response()->download($path, $filename)->deleteFileAfterSend(true);
                            
                        } catch (\Exception $e) {
                            // Log error
                            \Log::error('PDF Export Error: ' . $e->getMessage(), [
                                'exception' => $e,
                                'trace' => $e->getTraceAsString()
                            ]);
                            
                            // Notifikasi error ke user
                            Notification::make()
                                ->danger()
                                ->title('Gagal mengekspor PDF')
                                ->body('Error: ' . $e->getMessage() . '. Silakan periksa log untuk detail.')
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