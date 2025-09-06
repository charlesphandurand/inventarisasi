# Setup Migration untuk Sistem Inventarisasi

## Masalah yang Ditemukan
Error saat menjalankan seeder:
```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'user_id' in 'field list'
```

## Penyebab
Tabel `pengajuan_pinjaman` tidak memiliki kolom `user_id` yang diperlukan untuk menyimpan user yang mengajukan pinjaman.

## Solusi

### 1. Jalankan Migration untuk Menambah Kolom
```bash
php artisan migrate
```

Migration file `2025_08_30_120000_add_user_id_to_pengajuan_pinjaman_table.php` akan menambahkan kolom `user_id` ke tabel `pengajuan_pinjaman`.

### 2. Jalankan Seeder Lagi
```bash
php artisan db:seed
```

### 3. Atau Jalankan Semua dari Awal
```bash
php artisan migrate:fresh --seed
```

## Struktur Tabel yang Benar

### Tabel `pengajuan_pinjaman`
- `id` - Primary key
- `aset_id` - Foreign key ke tabel asets
- `user_id` - Foreign key ke tabel users (user yang mengajukan)
- `jumlah_pinjam` - Jumlah barang yang dipinjam
- `tanggal_pengajuan` - Tanggal pengajuan
- `tanggal_approval` - Tanggal approval (nullable)
- `admin_id` - Foreign key ke tabel users (admin yang approve)
- `status` - Status pengajuan (diajukan/disetujui/ditolak)
- `created_at` - Timestamp created
- `updated_at` - Timestamp updated

## Relasi yang Tersedia

### Model PengajuanPinjaman
```php
public function user()
{
    return $this->belongsTo(User::class);
}

public function aset()
{
    return $this->belongsTo(Aset::class);
}

public function admin()
{
    return $this->belongsTo(User::class, 'admin_id');
}
```

### Model User
```php
public function pengajuanPinjaman()
{
    return $this->hasMany(PengajuanPinjaman::class);
}

public function approvedPengajuan()
{
    return $this->hasMany(PengajuanPinjaman::class, 'admin_id');
}
```

## Testing

### 1. Login sebagai Admin
- Email: `admin@gmail.com`
- Password: `admin123`

### 2. Login sebagai User
- Email: `user1@example.com` sampai `user5@example.com`
- Password: `password`

### 3. Fitur yang Tersedia
- **Admin**: Semua fitur (CRUD user, aset, approve/reject pengajuan)
- **User**: Lihat aset, buat/edit/delete pengajuan pinjaman

## Troubleshooting

### Migration Error
```bash
# Clear cache
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Reset migration
php artisan migrate:reset
php artisan migrate
```

### Seeder Error
```bash
# Jalankan seeder tertentu
php artisan db:seed --class=RolePermissionSeeder

# Atau jalankan semua
php artisan db:seed
```

### Database Connection
- Pastikan database `inventarisasi` sudah dibuat
- Pastikan konfigurasi database di `.env` sudah benar
- Pastikan MySQL service berjalan
