@php
    $totalStok = (int) \App\Models\Aset::sum('jumlah_barang');
    $stokHariIni = (int) \App\Models\Aset::whereDate('updated_at', \Carbon\Carbon::today())->sum('jumlah_barang');
    $stokBulanIni = (int) \App\Models\Aset::whereMonth('updated_at', \Carbon\Carbon::now()->month)->sum('jumlah_barang');
@endphp

<x-filament-widgets::widget>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <x-filament::card>
            <div class="text-sm opacity-80">Total Stok</div>
            <div class="text-2xl font-bold text-primary-600">{{ number_format($totalStok) }}</div>
        </x-filament::card>
        <x-filament::card>
            <div class="text-sm opacity-80">Stok (update hari ini)</div>
            <div class="text-2xl font-bold text-success-600">{{ number_format($stokHariIni) }}</div>
        </x-filament::card>
        <x-filament::card>
            <div class="text-sm opacity-80">Stok (update bulan ini)</div>
            <div class="text-2xl font-bold text-warning-600">{{ number_format($stokBulanIni) }}</div>
        </x-filament::card>
    </div>
</x-filament-widgets::widget>


