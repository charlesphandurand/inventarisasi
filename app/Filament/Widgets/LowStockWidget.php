<?php

namespace App\Filament\Widgets;

use App\Models\Aset;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth; // Tambahkan import untuk Auth

class LowStockWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'Aset Stok Rendah / Bermasalah';

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        // Mendapatkan status peran pengguna saat ini
        $isAdminOrApprover = Auth::user()->hasRole('admin') || Auth::user()->hasRole('approver');

        return $table
            ->query(
                Aset::query()
                    ->where(function ($q) {
                        $q->where('jumlah_barang', '<=', 5)
                            ->orWhereRaw('LOWER(keterangan) LIKE ?', ['%rusak%'])
                            ->orWhereRaw('LOWER(keterangan) LIKE ?', ['%expired%']);
                    })
                    ->orderBy('jumlah_barang', 'asc')
                    ->orderBy('nama_barang')
                    ->limit(20)
            )
            ->headerActions([
                Action::make('export_low_stock')
                    ->label('Unduh Low Stock')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->url(fn () => route('asets.export.lowstock'))
                    ->openUrlInNewTab()
                    // LOGIKA PEMBATASAN VISIBILITAS: Hanya admin atau approver
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
                SelectFilter::make('lokasi')
                    ->label('Filter Lokasi')
                    ->options(Aset::query()->pluck('lokasi', 'lokasi'))
                    ->searchable()
            ]);
    }
}
