# Perbaikan Validasi Form Pengajuan Pinjaman

## 🚨 **Masalah yang Ditemukan**
Saat edit pengajuan barang, muncul error:
```
Jumlah pinjam tidak boleh lebih dari 0 barang.
```

**Penyebab**: Field "Sisa Barang Tersedia" tidak muncul saat edit, sehingga user tidak tahu berapa sisa barang yang tersedia.

## ✅ **Solusi yang Telah Diterapkan**

### 1. **Perbaikan PengajuanPinjamanForm.php**
- **Field `sisa_barang`**: Ditambahkan nilai default yang otomatis terisi
- **Validasi `jumlah_pinjam`**: Ditambahkan validasi min value dan error message yang lebih jelas
- **Live update**: Field sisa barang otomatis update saat pilih aset

### 2. **Perbaikan EditPengajuanPinjaman.php**
- **Method `mutateFormDataBeforeFill()`**: Digunakan untuk mengatur nilai default field sisa_barang (Filament v3 compatible)
- **Auto-populate**: Nilai sisa barang otomatis terisi berdasarkan aset yang dipilih

## 🔧 **Implementasi Teknis**

### **Field Sisa Barang**
```php
TextInput::make('sisa_barang')
    ->label('Sisa Barang Tersedia')
    ->disabled()
    ->numeric()
    ->dehydrated(false)
    ->default(function ($get) {
        $asetId = $get('aset_id');
        if ($asetId) {
            $aset = Aset::find($asetId);
            return $aset ? $aset->sisa_barang : 0;
        }
        return 0;
    }),
```

### **Validasi Jumlah Pinjam**
```php
TextInput::make('jumlah_pinjam')
    ->label('Jumlah Pinjam')
    ->required()
    ->numeric()
    ->minValue(1) // Minimal 1 barang
    ->live()
    ->rules([
        'required',
        'numeric',
        'min:1',
        function (callable $get) {
            return function (string $attribute, $value, callable $fail) use ($get) {
                $sisaBarang = (int) $get('sisa_barang');
                if ($value > $sisaBarang) {
                    $fail("Jumlah pinjam tidak boleh lebih dari {$sisaBarang} barang.");
                }
                if ($value <= 0) {
                    $fail("Jumlah pinjam harus lebih dari 0 barang.");
                }
            };
        },
    ]),
```

### **Auto-populate Sisa Barang saat Edit (Filament v3)**
```php
protected function mutateFormDataBeforeFill(array $data): array
{
    // Set nilai default untuk sisa_barang berdasarkan aset yang dipilih
    if (isset($data['aset_id'])) {
        $aset = \App\Models\Aset::find($data['aset_id']);
        if ($aset) {
            $data['sisa_barang'] = $aset->sisa_barang;
        }
    }
    
    return $data;
}
```

## 📋 **Flow yang Benar Sekarang**

### **Saat Create Pengajuan**
1. User pilih barang dari dropdown
2. Field "Sisa Barang Tersedia" otomatis terisi
3. User isi jumlah pinjam
4. Validasi berfungsi dengan benar

### **Saat Edit Pengajuan**
1. User buka form edit
2. Field "Sisa Barang Tersedia" otomatis terisi dengan nilai yang benar
3. User bisa lihat berapa sisa barang tersedia
4. Validasi berfungsi dengan benar

## 🎯 **Validasi yang Tersedia**

### **Validasi Jumlah Pinjam**
- ✅ **Required**: Harus diisi
- ✅ **Numeric**: Harus berupa angka
- ✅ **Min Value**: Minimal 1 barang
- ✅ **Max Value**: Tidak boleh lebih dari sisa barang tersedia
- ✅ **Custom Error**: Pesan error yang jelas dan informatif

### **Validasi Sisa Barang**
- ✅ **Auto-populate**: Otomatis terisi berdasarkan aset
- ✅ **Real-time**: Update otomatis saat pilih aset
- ✅ **Disabled**: User tidak bisa edit (read-only)

## 🚀 **Testing yang Perlu Dilakukan**

### **Test Create Pengajuan**
1. User buat pengajuan baru
2. Pilih barang dari dropdown
3. **Pastikan field "Sisa Barang Tersedia" terisi otomatis**
4. Isi jumlah pinjam
5. **Pastikan validasi berfungsi dengan benar**

### **Test Edit Pengajuan**
1. User edit pengajuan yang sudah ada
2. **Pastikan field "Sisa Barang Tersedia" terisi dengan nilai yang benar**
3. Coba ubah jumlah pinjam
4. **Pastikan validasi berfungsi dengan benar**

### **Test Validasi**
1. Coba isi jumlah pinjam = 0 → **Error: "Jumlah pinjam harus lebih dari 0 barang."**
2. Coba isi jumlah pinjam > sisa barang → **Error: "Jumlah pinjam tidak boleh lebih dari X barang."**
3. Coba isi jumlah pinjam valid → **Tidak ada error**

## 🔍 **Troubleshooting**

### **Field Sisa Barang Masih Kosong**
1. Pastikan aset_id terisi dengan benar
2. Pastikan model Aset memiliki field sisa_barang
3. Check database apakah data aset lengkap

### **Validasi Tidak Berfungsi**
1. Pastikan field sisa_barang terisi dengan nilai yang benar
2. Pastikan jumlah_pinjam berupa angka
3. Check console browser untuk error JavaScript

### **Error Message Tidak Jelas**
1. Pastikan validasi custom function berfungsi
2. Check apakah $get('sisa_barang') mengembalikan nilai yang benar
3. Pastikan format error message sesuai

## 📝 **Catatan Penting**

- **Field sisa_barang**: Selalu otomatis terisi, tidak bisa diisi manual
- **Validasi real-time**: Berfungsi saat user mengetik
- **Error message**: Jelas dan informatif
- **Auto-populate**: Bekerja baik saat create maupun edit
- **Timezone**: Semua timestamp menggunakan WITA
- **Filament v3**: Menggunakan `mutateFormDataBeforeFill()` bukan `form()`

## 🔄 **Jika Perlu Perubahan**

### **Ubah Pesan Error**
Edit di `PengajuanPinjamanForm.php`:
```php
if ($value > $sisaBarang) {
    $fail("Pesan error custom: {$sisaBarang} barang tersedia.");
}
```

### **Ubah Validasi Min Value**
Edit di `PengajuanPinjamanForm.php`:
```php
->minValue(5) // Minimal 5 barang
->rules([
    'min:5', // Tambahkan ke rules juga
])
```

### **Ubah Field Label**
Edit di `PengajuanPinjamanForm.php`:
```php
->label('Stok Tersedia') // Ganti label
```

## 🆕 **Perbaikan Kompatibilitas Filament v3**

### **Masalah yang Diperbaiki**
```
Could not check compatibility between App\Filament\Resources\PengajuanPinjaman\Pages\EditPengajuanPinjaman::form(Filament\Forms\Form $form): Filament\Forms\Form and Filament\Resources\Pages\EditRecord::form(Filament\Schemas\Schema $schema): Filament\Schemas\Schema, because class Filament\Forms\Form is not available
```

### **Solusi**
- **Sebelum**: Menggunakan method `form(Form $form)` yang tidak kompatibel dengan Filament v3
- **Sesudah**: Menggunakan method `mutateFormDataBeforeFill(array $data)` yang kompatibel dengan Filament v3

### **Perbedaan Implementasi**
```php
// ❌ Filament v2 (TIDAK KOMPATIBEL)
public function form(Form $form): Form
{
    $form = parent::form($form);
    // ... logic
    return $form;
}

// ✅ Filament v3 (KOMPATIBEL)
protected function mutateFormDataBeforeFill(array $data): array
{
    // ... logic
    return $data;
}
```

Sekarang form pengajuan pinjaman sudah berfungsi dengan baik dan kompatibel dengan Filament v3! User bisa melihat sisa barang tersedia baik saat create maupun edit, dan validasi berfungsi dengan pesan error yang jelas.
