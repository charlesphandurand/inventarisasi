<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Aset;

class PengajuanPinjaman extends Model
{
    use HasFactory;

    protected $table = 'pengajuan_pinjaman'; 
    protected $fillable = [
        'aset_id',
        'user_id', // Tambahkan user_id
        'jumlah_pinjam',
        'tanggal_pengajuan',
        'tanggal_approval',
        'admin_id',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
    
    public function aset()
    {
        return $this->belongsTo(Aset::class, 'aset_id');
    }
}