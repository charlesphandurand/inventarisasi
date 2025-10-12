<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            font-family: Arial, sans-serif; 
            font-size: 8pt; /* Ukuran font lebih kecil untuk landscape */
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
            padding: 5px; /* Padding lebih kecil */
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
                <th style="width: 15%;">Nama Barang</th>
                <th style="width: 5%;" class="text-center">ATK</th>
                <th style="width: 10%;">Kondisi</th>
                <th style="width: 10%;" class="text-center">Expired Date</th>
                <th style="width: 5%;" class="text-center">Jumlah</th>
                <th style="width: 10%;">Vendor</th>
                <th style="width: 10%;" class="text-right">Harga (IDR)</th>
                <th style="width: 12%;">Lokasi</th>
                <th style="width: 20%;">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ strtoupper($item->nama_barang) }}</td>
                    <td class="text-center">{{ $item->is_atk ? 'Ya' : 'Tidak' }}</td>
                    <td>{{ strtoupper($item->kondisi_barang) }}</td>
                    <td class="text-center">{{ $item->expired_date ? \Carbon\Carbon::parse($item->expired_date)->format('d/m/Y') : '-' }}</td>
                    <td class="text-center">{{ number_format($item->jumlah_barang, 0, ',', '.') }}</td>
                    <td>{{ $item->nama_vendor }}</td>
                    <td class="text-right">
                        {{ number_format($item->harga, 0, ',', '.') }}
                    </td>
                    <td>{{ strtoupper($item->lokasi) }}</td>
                    <td>{{ $item->keterangan }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center">Tidak ada data aset ditemukan dalam laporan ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    
    <div class="footer">
        <p>Laporan ini mencakup {{ $data->count() }} item aset.</p>
        <p>Dibuat otomatis pada {{ now()->format('d/m/Y') }}.</p>
    </div>
</body>
</html>
