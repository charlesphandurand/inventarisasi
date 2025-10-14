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
            font-size: 10pt;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        
        .header h1 {
            font-size: 16pt;
            margin-bottom: 5px;
        }
        
        .header p {
            font-size: 9pt;
            color: #666;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        th {
            background-color: #f2f2f2;
            font-weight: bold;
            font-size: 9pt;
        }
        
        td {
            font-size: 9pt;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 8pt;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        
        .badge-danger {
            background-color: #dc3545;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title }}</h1>
        <p>Tanggal Laporan: {{ now()->format('d F Y H:i:s') }}</p>
        <p>Filter: Stok ≤ 5 dan Lokasi mengandung "ATK"</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;" class="text-center">No</th>
                <th style="width: 25%;">Nama Barang</th>
                <th style="width: 10%;" class="text-center">Stok Sisa</th>
                <th style="width: 15%;">Lokasi</th>
                <th style="width: 60%;">Keterangan</th>
                <!-- <th style="width: 10%;">Satuan</th> -->
                <!-- <th style="width: 12%;" class="text-right">Harga Satuan</th>
                <th style="width: 13%;" class="text-right">Total Nilai</th>
                <th style="width: 10%;" class="text-center">Tgl Perolehan</th> -->
            </tr>
        </thead>
        <tbody>
            @forelse ($data as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $item->nama_barang }}</td>
                    <td class="text-center">
                        <span class="badge-danger">{{ $item->jumlah_barang }}</span>
                    </td>
                    <td>{{ $item->lokasi }}</td>
                    <td>{{ $item->keterangan }}</td>
                    <!-- <td>{{ $item->satuan ?? '-' }}</td> -->
                    <!-- <td class="text-right">
                        {{ $item->harga_satuan ? 'Rp ' . number_format($item->harga_satuan, 0, ',', '.') : '-' }}
                    </td>
                    <td class="text-right">
                        {{ $item->total_nilai ? 'Rp ' . number_format($item->total_nilai, 0, ',', '.') : '-' }}
                    </td>
                    <td class="text-center">
                        {{ $item->tanggal_perolehan ? \Carbon\Carbon::parse($item->tanggal_perolehan)->format('d/m/Y') : '-' }}
                    </td> -->
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center">Tidak ada data stok rendah ATK saat ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    
    <div class="footer">
        <p>Laporan dihasilkan otomatis oleh Sistem Inventarisasi</p>
        <p>Total Data: {{ $data->count() }} item</p>
    </div>
</body>
</html>