<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-3">
            <div class="text-base font-semibold text-danger-600">Aset Stok Rendah (<= 5)</div>
            @php
                $rows = \App\Models\Aset::query()->where('jumlah_barang', '<=', 5)->orderBy('jumlah_barang')->limit(20)->get();
            @endphp
            @if($rows->count())
                <div class="overflow-x-auto rounded-lg ring-1 ring-danger-500/30">
                    <table class="min-w-full text-sm">
                        <thead class="bg-danger-600 text-white">
                            <tr>
                                <th class="px-3 py-2 text-left">NAMA BARANG</th>
                                <th class="px-3 py-2 text-left">LOKASI</th>
                                <th class="px-3 py-2 text-right">SISA</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                                <tr class="border-b last:border-0">
                                    <td class="px-3 py-2 font-medium">{{ strtoupper($row->nama_barang) }}</td>
                                    <td class="px-3 py-2">{{ strtoupper($row->lokasi) }}</td>
                                    <td class="px-3 py-2 text-right text-danger-600 font-semibold">{{ $row->jumlah_barang }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-sm">Tidak ada aset stok rendah.</div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>


