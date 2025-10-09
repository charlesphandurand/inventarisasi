<?php

use Illuminate\Support\Facades\Route;
use App\Models\Aset;

Route::get('/admin/asets/export-laporan', function () {
    $asets = Aset::query()
        ->where('jumlah_barang', '<=', 5)
        ->where(function ($q) {
            $q->whereRaw('LOWER(keterangan) LIKE ?', ['%rusak%'])
              ->orWhereRaw('LOWER(keterangan) LIKE ?', ['%expired%']);
        })
        ->select(['nama_barang', 'jumlah_barang', 'lokasi', 'keterangan'])
        ->orderBy('nama_barang')
        ->get();

    // Buat XLSX sederhana (Office Open XML)
    $zip = new \ZipArchive();
    $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip->open($tmpFile, \ZipArchive::OVERWRITE);

    $sharedStrings = [];
    $getSi = function ($value) use (&$sharedStrings) {
        $key = mb_strtolower($value ?? '');
        if (!array_key_exists($key, $sharedStrings)) {
            $sharedStrings[$key] = ['v' => (string) $value, 'i' => count($sharedStrings)];
        }
        return $sharedStrings[$key]['i'];
    };

    // Header
    $rowsXml = [];
    $headers = ['Nama barang', 'Jumlah barang', 'Lokasi', 'Keterangan'];
    $hCells = [];
    foreach ($headers as $idx => $text) {
        $ref = chr(65 + $idx) . '1';
        $si = $getSi($text);
        $hCells[] = "<c r=\"$ref\" t=\"s\"><v>$si</v></c>";
    }
    $rowsXml[] = '<row r="1">' . implode('', $hCells) . '</row>';

    // Data rows
    $r = 2;
    foreach ($asets as $a) {
        $cells = [];
        $cells[] = '<c r="A'. $r .'" t="s"><v>'. $getSi($a->nama_barang) .'</v></c>';
        $cells[] = '<c r="B'. $r .'" t="n"><v>'. (int) $a->jumlah_barang .'</v></c>';
        $cells[] = '<c r="C'. $r .'" t="s"><v>'. $getSi($a->lokasi) .'</v></c>';
        $cells[] = '<c r="D'. $r .'" t="s"><v>'. $getSi($a->keterangan) .'</v></c>';
        $rowsXml[] = '<row r="'. $r .'">' . implode('', $cells) . '</row>';
        $r++;
    }

    // sharedStrings.xml
    $sstItems = '';
    uasort($sharedStrings, fn($a, $b) => $a['i'] <=> $b['i']);
    foreach ($sharedStrings as $item) {
        $sstItems .= '<si><t>'. htmlspecialchars($item['v']) .'</t></si>';
    }
    $zip->addFromString('xl/sharedStrings.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($sharedStrings).'" uniqueCount="'.count($sharedStrings).'">'
        .$sstItems
        .'</sst>'
    );

    // sheet1.xml
    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<sheetData>' . implode('', $rowsXml) . '</sheetData>'
        .'</worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

    // workbook.xml
    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<sheets><sheet name="Laporan" sheetId="1" r:id="rId1"/></sheets>'
        .'</workbook>';
    $zip->addFromString('xl/workbook.xml', $workbookXml);

    // _rels/workbook.xml.rels
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
        .'</Relationships>');

    // _rels/.rels
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        .'</Relationships>');

    // [Content_Types].xml
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        .'<Default Extension="xml" ContentType="application/xml"/>'
        .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
        .'</Types>');

    $zip->close();

    $filename = 'laporan-aset-lowstock-rusak-expired-' . now()->format('Ymd_His') . '.xlsx';
    return response()->download($tmpFile, $filename, [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'Cache-Control' => 'no-store, no-cache',
    ])->deleteFileAfterSend(true);
})->name('asets.export.laporan')->middleware(['web']);

// Export Low Stock ke Excel-compatible HTML table (.xls)
Route::get('/admin/asets/export-lowstock', function () {
    $asets = Aset::query()
        ->where(function ($q) {
            $q->where('jumlah_barang', '<=', 5)
              ->orWhereRaw('LOWER(keterangan) LIKE ?', ['%rusak%'])
              ->orWhereRaw('LOWER(keterangan) LIKE ?', ['%expired%']);
        })
        ->orderBy('jumlah_barang', 'asc')
        ->orderBy('nama_barang')
        ->get(['nama_barang','jumlah_barang','lokasi','keterangan']);

    $html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"><style>
        table{border-collapse:collapse;width:100%;}
        th,td{border:1px solid #000;padding:6px;text-align:left;}
        th{background:#f2f2f2;}
    </style></head><body>';
    $html .= '<table><thead><tr>'
        .'<th>Nama Barang</th><th>Lokasi</th><th>Sisa</th><th>Keterangan</th>'
        .'</tr></thead><tbody>';
    foreach ($asets as $a) {
        $html .= '<tr>'
            .'<td>'. htmlspecialchars($a->nama_barang ?? '') .'</td>'
            .'<td>'. htmlspecialchars($a->lokasi ?? '') .'</td>'
            .'<td>'. (int)$a->jumlah_barang .'</td>'
            .'<td>'. htmlspecialchars($a->keterangan ?? '-') .'</td>'
            .'</tr>';
    }
    $html .= '</tbody></table></body></html>';

    $filename = 'lowstock-' . now()->format('Ymd_His') . '.xls';
    return response($html, 200, [
        'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        'Cache-Control' => 'no-store, no-cache',
    ]);
})->name('asets.export.lowstock')->middleware(['web']);

Route::get('/', function () {
    return view('home');
});
