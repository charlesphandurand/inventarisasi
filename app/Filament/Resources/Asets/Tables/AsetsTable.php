<?php

namespace App\Filament\Resources\Asets\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Action;
use Filament\Actions\ViewAction; 
use Filament\Notifications\Notification; 
use Filament\Tables\Columns\IconColumn; 

// --- Imports untuk Export Excel ---
use pxlrbt\FilamentExcel\Actions\ExportAction; 
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column; 

// --- IMPORTS UNTUK SPATIE PDF (Sesuai permintaan user) ---
use Spatie\LaravelPdf\Facades\Pdf; 
use Spatie\LaravelPdf\Enums\Format; 
use Spatie\Browsershot\Browsershot; 

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;

// --- Imports untuk QR Code (Bacon/SVG) ---
use BaconQrCode\Renderer\Image\SvgImageBackEnd; 
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer; 
use Illuminate\Support\HtmlString; 
use App\Models\Aset; 

class AsetsTable
{
    /**
     * Fungsi pembantu untuk membuat QR Code SVG.
     */
    private static function generateQrCodeSvg(string $data): HtmlString
    {
        $renderer = new ImageRenderer(
            new RendererStyle(150),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $svgString = $writer->writeString($data);
        return new HtmlString($svgString);
    }

    public static function configure(Table $table): Table
    {
        $isAdmin = Auth::user()->hasAnyRole(['admin']);
        $isAdminOrApprover = Auth::user()->hasAnyRole(['admin', 'approver']); 

        return $table
            ->heading('Manajemen Data Aset') 
            ->columns([
                
                // KOLOM 1: is_atk (Text Column dengan Badge)
                TextColumn::make('is_atk')
                    ->label('ATK')
                    ->badge() 
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Ya' : 'Tidak')
                    ->color(fn (bool $state): string => $state ? 'success' : 'warning') 
                    ->sortable(),

                // KOLOM 2: kondisi_barang
                TextColumn::make('kondisi_barang')
                    ->label('Kondisi')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn ($state) => strtoupper($state)),

                // KOLOM 3: expired_date
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
                TextColumn::make('nama_vendor')
                    ->label('Nama Vendor')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('harga')
                    ->label('Harga')
                    ->money('IDR', locale: 'id')
                    ->sortable(),
                TextColumn::make('lokasi')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn ($state) => strtoupper($state)),
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
                // --- EXPORT KE EXCEL (XLSX/CSV) ---
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
                                // Kolom yang disinkronkan ke Excel
                                Column::make('is_atk')->heading('ATK')
                                    ->formatStateUsing(fn (bool $state) => $state ? 'Ya' : 'Tidak'), // Format Ya/Tidak di Excel
                                Column::make('kondisi_barang')->heading('KONDISI BARANG'),
                                Column::make('expired_date')->heading('EXPIRED DATE')
                                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : '-'), // Format Tanggal
                                    
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
                
                // --- EXPORT KE PDF (MENGGUNAKAN SPATIE LARAVEL PDF) ---
                Action::make('export_pdf')
                    ->label('Ekspor PDF')
                    ->color('danger')
                    ->icon('heroicon-o-document-arrow-down')
                    ->visible(fn () => $isAdminOrApprover)
                    ->action(function (Table $table) use ($isAdminOrApprover) {
                        // Ambil query dari table saat ini (termasuk filter)
                        $query = $table->getLivewire()->getFilteredTableQuery();

                        try {
                            $records = (clone $query)->get();
                            
                            if ($records->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Tidak ada data')
                                    ->body('Tidak ada data aset untuk diekspor.')
                                    ->send();
                                return;
                            }
                            
                            $filename = 'Laporan_Aset_' . now()->format('Ymd_His') . '.pdf';
                            $path = storage_path('app/public/' . $filename);
                            
                            // Menggunakan Spatie\LaravelPdf untuk render
                            Pdf::view('exports.aset_laporan_pdf', [
                                'data' => $records,
                                'title' => 'Laporan Data Aset Keseluruhan',
                            ])
                            ->format(Format::A4) // Menggunakan Enum Spatie
                            ->landscape() 
                            ->save($path);
                            
                            // Download file dan hapus setelah selesai
                            return Response::download($path, $filename)->deleteFileAfterSend(true);
                            
                        } catch (\Exception $e) {
                            Log::error('PDF Export Error: ' . $e->getMessage(), [
                                'exception' => $e,
                                'trace' => $e->getTraceAsString()
                            ]);
                            
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
            ->recordActions([
                // Aksi default
                ViewAction::make(), 
                EditAction::make()->visible(fn () => $isAdmin),
                
                // --- AKSI TAMPILKAN QR CODE ---
                Action::make('show_qr_code')
                    ->label('QR Code')
                    ->icon('heroicon-o-qr-code')
                    ->color('gray')
                    ->modalHeading(fn (Aset $record) => 'QR Code: ' . $record->nama_barang)
                    ->modalContent(function (Aset $record) {
                        if (empty($record->qr_code)) {
                            return new HtmlString('<div class="text-center p-4">QR Code belum dibuat.</div>');
                        }
                        $svg = self::generateQrCodeSvg($record->qr_code);
                        return new HtmlString('<div class="flex flex-col items-center justify-center p-4">
                            <h3 class="text-lg font-semibold mb-2">Pindai untuk melihat detail aset</h3>
                            ' . $svg . '
                            <p class="text-sm mt-2 text-gray-500 break-all w-full text-center">Data: ' . $record->qr_code . '</p>
                        </div>');
                    })
                    ->visible(fn (Aset $record) => !empty($record->qr_code)),
            ])
            ->filters([
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
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => $isAdmin),
                ]),
            ]);
    }
}
