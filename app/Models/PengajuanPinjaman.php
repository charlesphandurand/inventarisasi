<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Aset; // Tambahkan baris ini

class PengajuanPinjaman extends Model
{
    use HasFactory;

    protected $table = 'pengajuan_pinjaman'; 
    protected $fillable = [
        'aset_id',
        'jumlah_pinjam',
        'tanggal_pengajuan',
        'tanggal_approval',
        'admin_id',
        'status',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
    
    // Tambahkan relasi ini untuk aset
    public function aset()
    {
        return $this->belongsTo(Aset::class, 'aset_id');
    }
}