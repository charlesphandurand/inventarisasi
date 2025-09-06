# Setup Role dan Permission untuk Sistem Inventarisasi

## Overview
Sistem ini menggunakan 2 role utama:
- **Admin**: Memiliki akses penuh ke semua fitur
- **User**: Hanya bisa mengajukan pinjaman dan melihat aset

## Permission yang Tersedia

### Admin (Semua Permission)
- ✅ **User Management**: view, create, edit, delete users
- ✅ **Aset Management**: view, create, edit, delete asets  
- ✅ **Pengajuan Management**: view, create, edit, delete, approve, reject pengajuan

### User (Permission Terbatas)
- ✅ **Aset**: view asets
- ✅ **Pengajuan**: view, create, edit, delete pengajuan
- ❌ **User Management**: Tidak ada akses
- ❌ **Aset Management**: Hanya bisa lihat
- ❌ **Approval**: Tidak bisa approve/reject

## Cara Setup

### 1. Jalankan Migration (jika belum)
```bash
php artisan migrate
```

### 2. Jalankan Seeder
```bash
# Jalankan semua seeder sekaligus
php artisan db:seed

# Atau jalankan dari awal
php artisan migrate:fresh --seed
```

## Login Credentials

### Admin
- Email: `admin@gmail.com`
- Password: `admin123`
- Role: `admin`

### Admin 2
- Email: `admin2@test.com`
- Password: `admin123`
- Role: `admin`

### User Testing
- Email: `user1@test.com`
- Password: `1`
- Role: `user`

- Email: `user2@test.com`
- Password: `1`
- Role: `user`

### User Factory (5 user)
- Password: `1` (untuk semua)
- Email: Otomatis dibuat dengan format random
- Role: `user`

## Testing Role

### Admin
- Bisa akses semua menu
- Bisa create/edit/delete user
- Bisa create/edit/delete aset
- Bisa approve/reject pengajuan

### User
- Hanya bisa lihat aset
- Bisa buat pengajuan pinjaman
- Bisa edit/delete pengajuan sendiri
- Tidak bisa akses user management

## File yang Telah Diupdate

1. **UserResource.php** - Menggunakan permission-based access
2. **AsetResource.php** - Menggunakan permission-based access  
3. **PengajuanPinjamanResource.php** - Menggunakan permission-based access
4. **DatabaseSeeder.php** - Semua seeder digabung menjadi satu
5. **UserFactory.php** - Password default = "1"

## Cara Menambah Permission Baru

1. Edit `DatabaseSeeder.php`
2. Tambahkan permission baru ke array `$permissions`
3. Jalankan `php artisan db:seed`

## Cara Menambah Role Baru

1. Edit `DatabaseSeeder.php`
2. Buat role baru dengan `Role::firstOrCreate(['name' => 'nama_role'])`
3. Berikan permission yang sesuai dengan `$role->givePermissionTo([...])`
4. Jalankan seeder

## Troubleshooting

### Menu Tidak Muncul
- Pastikan user sudah login
- Pastikan user memiliki role yang sesuai
- Clear cache: `php artisan config:clear && php artisan route:clear`

### Permission Error
- Pastikan seeder sudah dijalankan
- Pastikan user memiliki role yang benar
- Check database table `roles`, `permissions`, `role_has_permissions`

### Role Tidak Berfungsi
- Pastikan package `spatie/laravel-permission` sudah terinstall
- Pastikan trait `HasRoles` sudah ada di model User
- Pastikan middleware permission sudah ter-register

## Catatan Penting
- Semua seeder sekarang ada di `DatabaseSeeder.php`
- Password factory user = "1"
- Password admin = "admin123"
- Jalankan `php artisan db:seed` untuk setup lengkap
