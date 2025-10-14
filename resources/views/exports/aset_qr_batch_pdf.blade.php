<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        /* Styling Umum */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            font-family: Arial, sans-serif; 
            font-size: 8pt; 
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
        
        /* Styling Tabel */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 5px; 
            text-align: left;
            vertical-align: middle; /* Penting untuk QR Code */
        }
        
        th {
            background-color: #e8f0ff; /* Warna header yang berbeda */
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

        /* Styling QR Code SVG */
        .qr-container {
            width: 60px; /* Ukuran container QR kecil */
            height: 60px;
            display: block;
            margin: 0 auto;
        }
        
        .qr-container svg {
            width: 100%;
            height: 100%;
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
    @php
        // Pastikan AsetResource tersedia (di-pass dari action)
        $resourceClass = $AsetResource ?? \App\Filament\Resources\Asets\AsetResource::class; 
        
        // Define function to generate QR SVG using the passed callable (PHP array style)
        function generateQr(string $data, callable $generator, int $size = 60) {
            return call_user_func($generator, $data, $size)->toHtml();
        }
    @endphp

    <div class="header">
        <h1>{{ $title }}</h1>
        <p>Laporan Dihasilkan: {{ now()->format('d F Y H:i:s') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 3%;" class="text-center">No</th>
                <th style="width: 10%;" class="text-center">QR Code</th>
                <th style="width: 20%;">Nama Barang</th>
                <th style="width: 7%;" class="text-center">ATK</th>
                <th style="width: 10%;">Kondisi</th>
                <th style="width: 7%;" class="text-center">Jumlah</th>
                <th style="width: 10%;">Lokasi</th>
                <th style="width: 33%;">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data as $index => $item)
                @php
                    // Konten QR Code adalah URL ke halaman view aset
                    $qrContent = $resourceClass::getUrl('view', ['record' => $item->id]);
                    $qrSvg = generateQr($qrContent, $generateQrCodeSvg); // Panggil helper yang dilewatkan
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-center">
                        <div class="qr-container">
                            {!! $qrSvg !!}
                        </div>
                    </td>
                    <td>{{ strtoupper($item->nama_barang) }}</td>
                    <td class="text-center">{{ $item->is_atk ? 'Ya' : 'Tidak' }}</td>
                    <td>{{ strtoupper($item->kondisi_barang) }}</td>
                    <td class="text-center">{{ number_format($item->jumlah_barang, 0, ',', '.') }}</td>
                    <td>{{ strtoupper($item->lokasi) }}</td>
                    <td>{{ $item->keterangan }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center">Tidak ada data aset ditemukan dalam laporan ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    
    <div class="footer">
        <p>Laporan ini mencakup {{ $data->count() }} item aset yang memiliki QR Code.</p>
        <p>Dibuat otomatis pada {{ now()->format('d/m/Y') }}.</p>
    </div>
</body>
</html>
