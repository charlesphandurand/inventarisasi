<?php

namespace App\Filament\Widgets;

use App\Models\Aset;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class TotalAsetWidget extends BaseWidget
{
    // Mengatur urutan widget di dashboard
    protected static ?int $sort = 1;

    // Mengatur lebar kolom widget
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        // Mengambil data total stok dari model Aset
        $totalStok = (int) Aset::sum('jumlah_barang');

        // Mengambil data stok yang diupdate hari ini
        $stokHariIni = (int) Aset::whereDate('updated_at', Carbon::today())->sum('jumlah_barang');

        // Mengambil data stok yang diupdate bulan ini
        $stokBulanIni = (int) Aset::whereMonth('updated_at', Carbon::now()->month)->sum('jumlah_barang');

        return [
            Stat::make('Total Stok', number_format($totalStok))
                ->description('Total seluruh aset yang tersedia')
                ->color('success'),
            Stat::make('Stok (update hari ini)', number_format($stokHariIni))
                ->description('Jumlah stok yang diperbarui hari ini')
                ->color('info'),
            Stat::make('Stok (update bulan ini)', number_format($stokBulanIni))
                ->description('Jumlah stok yang diperbarui bulan ini')
                ->color('warning'),
        ];
    }
}
