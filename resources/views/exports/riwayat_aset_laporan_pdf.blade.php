<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        /* CSS untuk Laporan PDF */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 7pt; /* Ukuran font disesuaikan agar cukup untuk kolom yang lebih banyak */
            padding: 15px;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #333;
            padding-bottom: 8px;
        }

        .header h1 {
            font-size: 14pt;
            margin-bottom: 3px;
        }

        .header p {
            font-size: 7pt;
            color: #666;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 4px; /* Padding lebih kecil */
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
            font-size: 7pt;
        }

        td {
            font-size: 7pt;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 6pt;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 8px;
        }
    </style>
</head>
<body>
<div class="header">
    <h1>{{ $title }}</h1>
    <p>Laporan Dihasilkan: {{ now()->format('d F Y H:i:s') }}</p>
</div>

<table>
    <thead>
        <tr>
            <th style="width: 3%;" class="text-center">No</th>
            <th style="width: 12%;">Nama Aset</th>
            <th style="width: 11%;">Tanggal/Waktu</th> {{-- Lebar kolom disesuaikan --}}
            <th style="width: 7%;">Tipe</th>
            <th style="width: 11%;">Lokasi Sebelum</th>
            <th style="width: 11%;">Lokasi Sesudah</th>
            <th style="width: 9%;">Harga Sebelum</th>
            <th style="width: 9%;">Harga Sesudah</th>
            <th style="width: 11%;">Penanggung Jawab</th>
            <th style="width: 16%;">Keterangan</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($data as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>

                {{-- 1. Nama Aset --}}
                <td>{{ strtoupper($item->aset->nama_barang ?? $item->nama_barang ?? '-') }}</td>

                {{-- 2. Tanggal & Waktu Riwayat (created_at) --}}
                <td class="text-center">
                    @if ($item->created_at)
                        {{-- Format diubah menjadi d/m/Y H:i:s untuk menyertakan jam --}}
                        {{ \Carbon\Carbon::parse($item->created_at)->format('d/m/Y H:i:s') }}
                    @else
                        -
                    @endif
                </td>

                {{-- 3. Tipe --}}
                <td>{{ strtoupper($item->tipe ?? '-') }}</td>

                {{-- 4. Lokasi/Pengguna Lama --}}
                <td>{{ strtoupper($item->lokasi_sebelum ?? '-') }}</td>

                {{-- 5. Lokasi/Pengguna Baru --}}
                <td>{{ strtoupper($item->lokasi_sesudah ?? '-') }}</td>

                {{-- 6. Harga Sebelum (harga_sebelum) --}}
                <td class="text-right">
                    @if ($item->harga_sebelum)
                        Rp {{ number_format($item->harga_sebelum, 0, ',', '.') }}
                    @else
                        -
                    @endif
                </td>

                {{-- 7. Harga Sesudah (harga_sesudah) --}}
                <td class="text-right">
                    @if ($item->harga_sesudah)
                        Rp {{ number_format($item->harga_sesudah, 0, ',', '.') }}
                    @else
                        -
                    @endif
                </td>

                {{-- 8. Penanggung Jawab (user->name) --}}
                <td>{{ $item->user->name ?? $item->user_id ?? '-' }}</td>

                {{-- 9. Keterangan --}}
                <td>
                    {{ $item->keterangan ?? '-' }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="10" class="text-center">Tidak ada data riwayat aset ditemukan dalam laporan ini.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    <p>Laporan ini mencakup {{ $data->count() }} item riwayat aset.</p>
    <p>Dibuat otomatis pada {{ now()->format('d/m/Y') }}.</p>
</div>

</body>
</html>
