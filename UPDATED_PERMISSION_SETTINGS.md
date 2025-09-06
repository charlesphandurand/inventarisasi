# Pengaturan Permission User dan Admin (Updated)

## Overview
Sistem ini memiliki 2 level akses yang berbeda untuk admin dan user biasa, dengan pengaturan yang lebih fleksibel.

## 🔐 **Permission Admin (Full Access)**

### **Manajemen Aset**
- ✅ Lihat aset
- ✅ Buat aset baru
- ✅ Edit aset
- ✅ Delete aset
- ✅ Bulk delete aset

### **Manajemen Pengajuan Pinjaman**
- ✅ Lihat semua pengajuan
- ✅ Buat pengajuan baru
- ✅ Edit semua pengajuan
- ✅ Delete semua pengajuan
- ✅ Approve/Setujui pengajuan
- ✅ Bulk delete pengajuan

### **Manajemen User**
- ✅ Lihat user
- ✅ Buat user baru
- ✅ Edit user
- ✅ Delete user

## 👤 **Permission User (Limited Access)**

### **Manajemen Aset**
- ✅ Lihat aset (read-only)
- ❌ Buat aset baru
- ❌ Edit aset
- ❌ Delete aset

### **Pengajuan Pinjaman**
- ✅ Lihat pengajuan miliknya sendiri **SAJA**
- ✅ Buat pengajuan pinjaman
- ✅ Edit pengajuan miliknya sendiri
- ❌ Delete pengajuan
- ❌ Approve/Setujui pengajuan

### **Manajemen User**
- ❌ Lihat user
- ❌ Buat user baru
- ❌ Edit user
- ❌ Delete user

## 🎯 **Fitur Khusus untuk User**

### **Saat Buat Pengajuan Pinjaman**
- **Otomatis terisi**:
  - `user_id`: User yang sedang login
  - `tanggal_pengajuan`: Waktu saat submit (WITA)
  - `status`: "diajukan"

- **Field yang disembunyikan**:
  - `tanggal_pengajuan` (form)
  - `tanggal_approval`
  - `admin_id` (disetujui oleh)
  - `status` (tidak bisa diubah)

- **Field yang bisa diisi**:
  - `aset_id`: Pilih barang
  - `jumlah_pinjam`: Jumlah yang dipinjam

### **Saat Edit Pengajuan**
- **User**: Hanya bisa edit pengajuan miliknya sendiri
- **Admin**: Bisa edit semua pengajuan

### **Saat Lihat Tabel Pengajuan**
- **User**: Hanya lihat pengajuan miliknya sendiri
- **Admin**: Lihat semua pengajuan

## 🚫 **Tombol yang Disembunyikan untuk User**

### **Halaman Aset**
- ❌ Tombol "New Asset" (Create)
- ❌ Tombol "Edit" pada setiap baris
- ❌ Tombol "Delete" (bulk action)

### **Halaman Pengajuan Pinjaman**
- ✅ Tombol "New Pengajuan" (Create) - **SEMUA USER BISA**
- ❌ Tombol "Setujui" (approve) - hanya admin
- ✅ Tombol "Edit" - user bisa edit miliknya sendiri
- ❌ Tombol "Delete" - hanya admin

## 🔧 **Implementasi Teknis**

### **File yang Diupdate**

1. **PengajuanPinjamanForm.php**
   - Field `tanggal_pengajuan`: Hidden untuk semua user
   - Field `tanggal_approval`: Hidden untuk semua user
   - Field `admin_id`: Hidden untuk semua user
   - Field `status`: Hanya admin yang bisa lihat dan ubah

2. **CreatePengajuanPinjaman.php**
   - Auto-set `user_id`, `tanggal_pengajuan` (WITA), `status`
   - Timezone: `Asia/Makassar`

3. **PengajuanPinjamanTable.php**
   - Tombol "Setujui": Hanya admin
   - Tombol "Edit": Admin bisa edit semua, user hanya edit miliknya
   - Tombol "Delete": Hanya admin
   - Timezone: `Asia/Makassar` untuk semua kolom tanggal
   - **Filter otomatis**: User biasa hanya lihat pengajuan miliknya sendiri

4. **ListPengajuanPinjaman.php**
   - Tombol "Create": Semua user bisa (admin dan user biasa)

5. **PengajuanPinjamanResource.php**
   - `getEloquentQuery()`: Filter data berdasarkan user yang login
   - `canView`: Admin lihat semua, user lihat miliknya sendiri
   - `canEdit`: Admin edit semua, user edit miliknya sendiri
   - `canDelete`: Hanya admin

6. **EditPengajuanPinjaman.php**
   - Tombol "Delete": Hanya admin

## 📋 **Flow Pengajuan Pinjaman untuk User**

### **1. User Login**
- Masuk ke sistem dengan role "user"

### **2. Lihat Aset**
- Bisa lihat semua aset (read-only)
- Tidak bisa edit/delete aset

### **3. Lihat Tabel Pengajuan**
- **Hanya lihat pengajuan miliknya sendiri**
- Tidak bisa lihat pengajuan user lain
- Filter otomatis diterapkan

### **4. Buat Pengajuan**
- Klik tombol "Create" (semua user bisa)
- Pilih barang dari dropdown
- Isi jumlah pinjam
- Submit form

### **5. Setelah Submit**
- Status otomatis "diajukan"
- Tanggal pengajuan otomatis terisi (WITA)
- User ID otomatis terisi
- Menunggu approval admin

### **6. Edit Pengajuan**
- Bisa edit pengajuan miliknya sendiri
- Tidak bisa edit pengajuan user lain
- Tidak bisa ubah status

### **7. Lihat Status**
- Bisa lihat pengajuan miliknya sendiri
- Tidak bisa lihat pengajuan user lain
- Tidak bisa approve

## 🔒 **Security Features**

### **Role-Based Access Control**
- Menggunakan `Auth::user()->hasRole('admin')`
- Permission check di setiap action

### **Data Isolation**
- User hanya bisa lihat data miliknya sendiri
- Admin bisa lihat semua data
- **Filter otomatis di level query**

### **Form Validation**
- Field yang tidak boleh diisi user disembunyikan
- Default values di-set otomatis
- Timezone WITA untuk semua timestamp

### **Action Visibility**
- Tombol yang tidak relevan disembunyikan
- Berdasarkan role user yang login
- User bisa edit pengajuan miliknya sendiri

## 🚀 **Testing**

### **Test sebagai Admin**
1. Login dengan `admin@gmail.com` / `admin123`
2. Cek semua tombol muncul
3. Test create/edit/delete aset
4. Test approve pengajuan
5. Test edit semua pengajuan
6. **Cek bisa lihat semua pengajuan di tabel**

### **Test sebagai User**
1. Login dengan `user1@test.com` / `1`
2. Cek tombol yang disembunyikan
3. Test create pengajuan
4. Test edit pengajuan miliknya sendiri
5. Cek tidak bisa edit pengajuan user lain
6. **Cek hanya lihat pengajuan miliknya sendiri di tabel**

## 📝 **Catatan Penting**

- **User bisa create pengajuan**: ✅ Semua user bisa mengajukan pinjaman
- **User bisa edit pengajuan sendiri**: ✅ Bisa edit pengajuan miliknya
- **User hanya lihat pengajuan sendiri**: ✅ **BARU!** Data isolation di level tabel
- **Field hidden**: Semua field sensitif disembunyikan untuk user
- **Auto-fill**: Data user dan timestamp diisi otomatis
- **Timezone**: Semua tanggal menggunakan WITA (Asia/Makassar)
- **Permission check**: Setiap action dicek role user
- **Query filter**: User biasa otomatis hanya lihat data miliknya

## 🔄 **Jika Perlu Perubahan**

### **User Tidak Bisa Create Pengajuan**
Edit `ListPengajuanPinjaman.php`:
```php
CreateAction::make()->visible(fn () => Auth::user()->hasRole('admin')),
```

### **User Tidak Bisa Edit Pengajuan**
Edit `PengajuanPinjamanTable.php`:
```php
EditAction::make()->visible(fn () => $isAdmin),
```

### **User Bisa Lihat Semua Pengajuan**
Edit `PengajuanPinjamanResource.php`:
```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery(); // Hapus filter
}
```

### **User Bisa Lihat Semua Pengajuan (Permission)**
Edit `PengajuanPinjamanResource.php`:
```php
public static function canView(Model $record): bool
{
    return auth()->user()->can('view pengajuan');
}
```
