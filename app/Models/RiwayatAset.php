<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiwayatAset extends Model
{
    use HasFactory;

    protected $fillable = [
        'aset_id',
        'user_id',
        'tipe',
        'jumlah_perubahan',
        'stok_sebelum',
        'stok_sesudah',
        'harga_sebelum',
        'harga_sesudah',
        'lokasi_sebelum',
        'lokasi_sesudah',
        'keterangan',
    ];

    public function aset()
    {
        return $this->belongsTo(Aset::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}


