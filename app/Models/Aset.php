<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory; 
use Illuminate\Database\Eloquent\Model;

class Aset extends Model
{
    use HasFactory;
    protected $fillable = [
        'nama_barang',
        'jumlah_barang',
        'lokasi',
        'keterangan',
    ];

    public function pengajuanPinjaman()
    {
        return $this->hasMany(PengajuanPinjaman::class);
    }
}
