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
        
        /* Styling Tabel (Mode Detil) */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 5px; 
            text-align: left;
            vertical-align: middle; 
        }
        
        th {
            background-color: #e8f0ff; 
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

        /* Styling QR Code SVG di mode Tabel */
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

        /* --- Styling untuk format Label/Sticker (Mode Horizontal) --- */
        
        /* Kontainer utama untuk label/stiker, menggunakan flexbox untuk tata letak cetak */
        .label-grid {
            /* Membuat label menyebar secara horizontal */
            display: flex;
            flex-wrap: wrap; 
            gap: 12px; /* Jarak antar label */
            justify-content: flex-start;
            margin-top: 15px;
        }

        /* Styling untuk setiap item label (seperti stiker individual) */
        .label-item {
            border: 1px solid #ccc;
            padding: 5px;
            /* Ukuran label yang cocok agar banyak muat di halaman A4 landscape */
            width: 200px; 
            height: 70px; 
            
            display: flex; /* Mengatur QR dan Teks secara horizontal */
            align-items: center;
            
            /* Penting untuk pencetakan agar label tidak terpotong */
            break-inside: avoid; 
            page-break-inside: avoid;
            
            border-radius: 4px;
            background-color: #f9f9f9;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        /* Kontainer QR di mode Label */
        .label-qr-container {
            width: 60px;
            height: 60px;
            margin-right: 8px;
            flex-shrink: 0; /* Memastikan QR tidak mengecil */
        }
        
        .label-qr-container svg {
            width: 100%;
            height: 100%;
        }

        /* Teks Nama Aset di mode Label */
        .label-text {
            flex-grow: 1;
            font-size: 9pt; 
            font-weight: bold;
            line-height: 1.2;
            color: #333;
            /* Agar teks panjang tidak melebihi batas container */
            overflow: hidden; 
            word-break: break-word;
            max-height: 60px; /* Batasan tinggi teks */
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
        // Cek apakah mode format adalah label (hanya ada 1 kolom dan itu adalah 'nama_barang')
        $isLabelFormat = (count($selectedColumns) === 1 && array_key_exists('nama_barang', $selectedColumns));

        // Pastikan AsetResource tersedia (di-pass dari action)
        $resourceClass = $AsetResource ?? \App\Filament\Resources\Asets\AsetResource::class; 
        
        // Define function to generate QR SVG using the passed callable (PHP array style)
        function generateQr(string $data, callable $generator, int $size = 60) {
            // Memanggil fungsi generateQrCodeSvg dari AsetsTable class
            // Ukuran 60x60 sudah ideal untuk tampilan label/tabel.
            return call_user_func($generator, $data, $size)->toHtml();
        }

        // Helper untuk memformat data berdasarkan kunci (mirip dengan format di kolom Filament)
        function formatData($key, $value) {
            if ($value === null || $value === '') {
                return '-';
            }
            if ($key === 'is_atk') {
                return $value ? 'Ya' : 'Tidak';
            }
            if ($key === 'harga') {
                // Formatting harga dengan Rupiah
                return 'Rp ' . number_format($value, 0, ',', '.');
            }
            if (in_array($key, ['expired_date', 'created_at', 'updated_at'])) {
                // Formatting tanggal
                return $value ? \Carbon\Carbon::parse($value)->format('d/m/Y') : '-';
            }
            // Default: kapitalisasi untuk kolom string
            return strtoupper($value);
        }
    @endphp

    <div class="header">
        <h1>{{ $title }}</h1>
        <p>Laporan Dihasilkan: {{ now()->format('d F Y H:i:s') }}</p>
    </div>

    @if ($isLabelFormat)
        {{-- ========================================================= --}}
        {{-- MODE LABEL / STICKER (Hanya Nama Aset) --}}
        {{-- ========================================================= --}}
        <div class="label-grid">
            @forelse ($data as $item)
                @php
                    // Konten QR Code adalah URL ke halaman view aset
                    $qrContent = $resourceClass::getUrl('view', ['record' => $item->id]);
                    $qrSvg = generateQr($qrContent, $generateQrCodeSvg); // Panggil helper yang dilewatkan
                @endphp
                <div class="label-item">
                    <div class="label-qr-container">
                        {!! $qrSvg !!}
                    </div>
                    <div class="label-text">
                        {{ strtoupper($item->nama_barang) }}
                    </div>
                </div>
            @empty
                <p style="text-align: center; font-size: 10pt; color: #666; margin-top: 20px;">Tidak ada data aset ditemukan dalam laporan ini.</p>
            @endforelse
        </div>

    @else
        {{-- ========================================================= --}}
        {{-- MODE TABEL DETIL (DEFAULT) --}}
        {{-- ========================================================= --}}
        <table>
            <thead>
                <tr>
                    <th style="width: 3%;" class="text-center">No</th>
                    <th style="width: 10%;" class="text-center">QR Code</th>
                    {{-- KOLOM DINAMIS DARI PILIHAN USER --}}
                    @foreach ($selectedColumns as $label)
                        <th>{{ $label }}</th>
                    @endforeach
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
                        {{-- DATA KOLOM DINAMIS --}}
                        {{-- $selectedColumns berisi [ 'key' => 'Label', 'key2' => 'Label2' ] --}}
                        @foreach (array_keys($selectedColumns) as $key)
                            <td>
                                @if ($key === 'nama_barang')
                                    {{ strtoupper($item->{$key}) }}
                                @else
                                    {{ formatData($key, $item->{$key}) }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        {{-- Hitung colspan: 2 fixed + jumlah kolom terpilih --}}
                        <td colspan="{{ 2 + count($selectedColumns) }}" class="text-center">Tidak ada data aset ditemukan dalam laporan ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endif
    
    <div class="footer">
        <p>Laporan ini mencakup {{ $data->count() }} item aset yang memiliki QR Code.</p>
        <p>Dibuat otomatis pada {{ now()->format('d/m/Y') }}.</p>
    </div>
</body>
</html>
