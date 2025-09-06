# Pengaturan Permission User dan Admin

## Overview
Sistem ini memiliki 2 level akses yang berbeda untuk admin dan user biasa.

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
- ✅ Edit pengajuan
- ✅ Delete pengajuan
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
- ✅ Lihat pengajuan sendiri
- ✅ Buat pengajuan pinjaman
- ❌ Edit pengajuan
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
  - `tanggal_pengajuan`: Waktu saat submit
  - `status`: "diajukan"

- **Field yang disembunyikan**:
  - `tanggal_approval`
  - `admin_id` (disetujui oleh)
  - `status` (tidak bisa diubah)

- **Field yang bisa diisi**:
  - `aset_id`: Pilih barang
  - `jumlah_pinjam`: Jumlah yang dipinjam

## 🚫 **Tombol yang Disembunyikan untuk User**

### **Halaman Aset**
- ❌ Tombol "New Asset" (Create)
- ❌ Tombol "Edit" pada setiap baris
- ❌ Tombol "Delete" (bulk action)

### **Halaman Pengajuan Pinjaman**
- ❌ Tombol "New Pengajuan" (Create)
- ❌ Tombol "Setujui" (approve)
- ❌ Tombol "Edit" pada setiap baris
- ❌ Tombol "Delete" pada setiap baris
- ❌ Tombol "Delete" (bulk action)

## 🔧 **Implementasi Teknis**

### **File yang Diupdate**

1. **AsetsTable.php**
   - `EditAction::make()->visible(fn () => $isAdmin)`
   - `DeleteBulkAction::make()->visible(fn () => $isAdmin)`

2. **ListAsets.php**
   - `CreateAction::make()->visible(fn () => $isAdmin)`

3. **PengajuanPinjamanTable.php**
   - `Action::make('setujui')->visible(fn () => $record->status === 'diajukan' && $isAdmin)`
   - `EditAction::make()->visible(fn () => $isAdmin)`
   - `DeleteAction::make()->visible(fn () => $isAdmin)`

4. **PengajuanPinjamanForm.php**
   - Field `tanggal_pengajuan`: `visible(fn () => !$isCreate)`
   - Field `tanggal_approval`: `visible(fn () => !$isCreate && $isAdmin)`
   - Field `admin_id`: `visible(fn () => !$isCreate && $isAdmin)`
   - Field `status`: `disabled(fn () => !$isAdmin)`

5. **CreatePengajuanPinjaman.php**
   - Auto-set `user_id`, `tanggal_pengajuan`, `status`

6. **ListPengajuanPinjaman.php**
   - `CreateAction::make()->visible(fn () => $isAdmin)`

## 📋 **Flow Pengajuan Pinjaman untuk User**

### **1. User Login**
- Masuk ke sistem dengan role "user"

### **2. Lihat Aset**
- Bisa lihat semua aset (read-only)
- Tidak bisa edit/delete aset

### **3. Buat Pengajuan**
- Klik tombol "Create" (jika ada)
- Pilih barang dari dropdown
- Isi jumlah pinjam
- Submit form

### **4. Setelah Submit**
- Status otomatis "diajukan"
- Tanggal pengajuan otomatis terisi
- User ID otomatis terisi
- Menunggu approval admin

### **5. Lihat Status**
- Bisa lihat pengajuan sendiri
- Tidak bisa edit/delete
- Tidak bisa approve

## 🔒 **Security Features**

### **Role-Based Access Control**
- Menggunakan `Auth::user()->hasRole('admin')`
- Permission check di setiap action

### **Data Isolation**
- User hanya bisa lihat data sendiri
- Admin bisa lihat semua data

### **Form Validation**
- Field yang tidak boleh diisi user disembunyikan
- Default values di-set otomatis

### **Action Visibility**
- Tombol yang tidak relevan disembunyikan
- Berdasarkan role user yang login

## 🚀 **Testing**

### **Test sebagai Admin**
1. Login dengan `admin@gmail.com` / `admin123`
2. Cek semua tombol muncul
3. Test create/edit/delete aset
4. Test approve pengajuan

### **Test sebagai User**
1. Login dengan `user1@test.com` / `1`
2. Cek tombol yang disembunyikan
3. Test create pengajuan
4. Cek tidak bisa edit/delete

## 📝 **Catatan Penting**

- **User tidak bisa create pengajuan**: Ini mungkin perlu diubah jika user harus bisa mengajukan pinjaman
- **Field hidden**: Semua field sensitif disembunyikan untuk user
- **Auto-fill**: Data user dan timestamp diisi otomatis
- **Permission check**: Setiap action dicek role user

## 🔄 **Jika Perlu Perubahan**

### **User Bisa Create Pengajuan**
Edit `ListPengajuanPinjaman.php`:
```php
CreateAction::make()->visible(fn () => true), // Semua user bisa
```

### **User Bisa Edit Pengajuan Sendiri**
Edit `PengajuanPinjamanTable.php`:
```php
EditAction::make()->visible(fn ($record) => $isAdmin || $record->user_id === Auth::id()),
```
