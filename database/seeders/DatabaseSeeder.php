<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Aset;
use App\Models\PengajuanPinjaman;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;


class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        Aset::factory()->count(100)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@gmail.com',
            'password' => Hash::make('1'),
        ]);

        // 3. Buat 20 data pengajuan pinjaman secara acak
        for ($i = 0; $i < 20; $i++) {
            $aset = Aset::inRandomOrder()->first();
            $statuses = ['diajukan', 'disetujui', 'ditolak'];
            $status = $statuses[array_rand($statuses)];

            $adminId = null;
            $tanggalApproval = null;

            if ($status === 'disetujui') {
                $adminId = User::inRandomOrder()->first()->id;
                $tanggalApproval = Carbon::now()->subDays(rand(1, 10));
            }

            PengajuanPinjaman::create([
                'aset_id' => $aset->id,
                'jumlah_pinjam' => rand(1, $aset->jumlah_barang),
                'tanggal_pengajuan' => Carbon::now()->subDays(rand(1, 30)),
                'tanggal_approval' => $tanggalApproval,
                'admin_id' => $adminId,
                'status' => $status,
            ]);
        }
        
    }
}
