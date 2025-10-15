<!DOCTYPE html>
<html>
<head>
    <title>{{ $title }}</title>
    <style>
        /* CSS Dasar untuk Cetak */
        body { font-family: sans-serif; margin: 20px; font-size: 10pt; }
        h1 { font-size: 14pt; margin-bottom: 5px; }
        .info { margin-bottom: 15px; font-size: 9pt; }
        .info p { margin: 2px 0; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 5px 8px;
            text-align: left;
            word-wrap: break-word;
            max-width: 150px; /* Batasi lebar untuk tampilan Landscape */
            font-size: 8pt;
        }
        th { background-color: #f0f0f0; text-align: center; font-weight: bold; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        /* Style untuk badge tipe transaksi */
        .badge {
            display: inline-block;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 7pt;
            font-weight: bold;
            color: #fff;
        }
        .badge-warning { background-color: #f59e0b; }
        .badge-success { background-color: #10b981; }
        .badge-primary { background-color: #3b82f6; }
        .badge-info { background-color: #06b6d4; }
        .badge-danger { background-color: #ef4444; }
        .badge-gray { background-color: #6b7280; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="info">
        <p><strong>Periode Data:</strong> {{ $dateFrom }} hingga {{ $dateTo }}</p>
        <p><strong>Dicetak pada:</strong> {{ now()->format('d M Y H:i:s') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                @foreach($selectedHeaders as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($data as $item)
                <tr>
                    @foreach($selectedHeaders as $key => $header)
                        @php
                            // Ambil nilai menggunakan data_get untuk properti bertingkat (dot notation)
                            $value = data_get($item, $key);
                            $class = '';
                            $formattedValue = $value;

                            // Logika Formatting sesuai dengan PHP class
                            if ($key === 'tipe') {
                                $formattedValue = $formatTipe($value ?? '');
                                $badgeClass = match ($value) {
                                    'pinjam_dikembalikan' => 'badge-warning',
                                    'create', 'penambahan', 'permintaan_atk_dikeluarkan' => 'badge-success',
                                    'pinjam_disetujui' => 'badge-primary',
                                    'lokasi_update', 'harga_update' => 'badge-info',
                                    'pinjam_dihapus', 'pengurangan' => 'badge-danger',
                                    default => 'badge-gray',
                                };
                                $formattedValue = "<span class='badge {$badgeClass}'>{$formattedValue}</span>";
                            } elseif (str_contains($key, 'harga')) {
                                // **PERBAIKAN DI SINI:** Menggunakan helper $formatRupiah dari PHP
                                $formattedValue = $formatRupiah($value); 
                                $class = 'text-right';
                            } elseif (str_contains($key, 'jumlah_perubahan') || str_contains($key, 'stok')) {
                                $class = 'text-center';
                                $formattedValue = $value ?? 0;
                            } elseif ($key === 'created_at') {
                                $formattedValue = $value ? \Carbon\Carbon::parse($value)->format('d M Y H:i') : '-';
                            } elseif ($key === 'keterangan') {
                                // **PERBAIKAN DI SINI:** Menggunakan helper $formatKeterangan dari PHP untuk menampilkan full text atau '-'
                                $formattedValue = $formatKeterangan($value);
                            } elseif ($key === 'lokasi_sebelum' || $key === 'lokasi_sesudah') {
                                $formattedValue = $value ?? '-';
                            } else {
                                $formattedValue = $value ?? '-';
                            }
                        @endphp
                        <td class="{{ $class }}">{!! $formattedValue !!}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>