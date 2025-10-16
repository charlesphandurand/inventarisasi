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
            vertical-align: top;
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
        
        /* Badge Styles */
        .badge-danger, .badge-warning, .badge-info {
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: bold;
            white-space: nowrap;
            display: inline-block;
            margin: 1px 0;
        }

        .badge-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .badge-warning {
            background-color: #ffc107;
            color: #333;
        }
        .badge-info {
            background-color: #17a2b8;
            color: white;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title }}</h1>
        <p>Tanggal Laporan: {{ now()->format('d F Y H:i:s') }}</p>
        <!-- DESKRIPSI FILTER BARU -->
        <p>Filter Data: ATK (Alat Tulis Kantor) dengan kondisi Stok <= 5 ATAU Mendekati Kadaluarsa (<= 30 Hari) ATAU Kondisi Rusak.</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;" class="text-center">No</th>
                <th style="width: 25%;">Nama Barang</th>
                <th style="width: 8%;" class="text-center">Stok Sisa</th>
                <th style="width: 12%;">Lokasi</th>
                <!-- KOLOM TANGGAL KADALUARSA -->
                <th style="width: 15%;" class="text-center">Tgl Kadaluarsa</th> 
                <!-- KOLOM KONDISI -->
                <th style="width: 10%;" class="text-center">Kondisi</th>
                <!-- KOLOM ALASAN PERINGATAN (PENTING) -->
                <th style="width: 25%;">Alasan Peringatan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data as $index => $item)
                @php
                    // Ambil alasan peringatan menggunakan fungsi yang dilewatkan dari widget
                    // Pastikan variabel $getAlertReasons dilewatkan dari LowStockWidget
                    $reasons = $getAlertReasons($item); 
                    $reasonString = implode(', ', $reasons);
                    
                    // Tentukan warna badge untuk Kondisi
                    $kondisiColor = match (strtolower($item->kondisi_barang)) {
                        'baik' => 'badge-info',
                        'rusak' => 'badge-danger',
                        'kurang baik' => 'badge-warning',
                        default => '',
                    };
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $item->nama_barang }}</td>
                    <td class="text-center">
                        <!-- Stok Sisa selalu danger karena ini adalah Low Stock Widget -->
                        <span class="badge-danger">{{ $item->jumlah_barang }}</span>
                    </td>
                    <td>{{ $item->lokasi }}</td>
                    
                    <!-- Data Tanggal Kadaluarsa (Menggunakan expired_date) -->
                    <td class="text-center">
                        {{ $item->expired_date ? \Carbon\Carbon::parse($item->expired_date)->format('d/m/Y') : '-' }}
                    </td>
                    
                    <!-- Data Kondisi -->
                    <td class="text-center">
                        @if($kondisiColor)
                            <span class="{{ $kondisiColor }}">{{ ucfirst($item->kondisi_barang) }}</span>
                        @else
                            {{ ucfirst($item->kondisi_barang) }}
                        @endif
                    </td>
                    
                    <!-- Data Alasan Peringatan -->
                    <td>
                        @if(!empty($reasons))
                            @foreach($reasons as $reason)
                                @php
                                    // Tentukan warna badge untuk masing-masing alasan
                                    $reasonBadgeClass = '';
                                    if (str_contains($reason, 'Rusak') || str_contains($reason, 'Sudah Kadaluarsa')) {
                                        $reasonBadgeClass = 'badge-danger';
                                    } elseif (str_contains($reason, 'Mendekati Kadaluarsa')) {
                                        $reasonBadgeClass = 'badge-warning';
                                    } elseif (str_contains($reason, 'Stok Rendah')) {
                                        $reasonBadgeClass = 'badge-info';
                                    }
                                @endphp
                                <span class="{{ $reasonBadgeClass }}">
                                    {{ $reason }}
                                </span><br>
                            @endforeach
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">Tidak ada data aset ATK bermasalah saat ini.</td>
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
