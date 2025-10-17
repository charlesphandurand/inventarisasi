<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Aset;
use App\Models\PengajuanPinjaman;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Daftar Unit Kerja untuk User
        $unitKerja = [
            'Tim KPKWP', 
            'Tim SP & PUR', 
            'Tim FDSEK', 
            'Tim FPPUKIS', 
            'Tim Kehumasan', 
            'Tim MI'
        ];

        // 1. Buat permission yang diperlukan (SAMA SEPERTI SEBELUMNYA)
        $permissions = [
            // User management (Maker & Approver)
            'view users', 'create users', 'edit users', 'delete users',
            
            // Aset/Barang management (Maker & Approver)
            'view asets', 'create asets', 'edit asets', 'delete asets',
            
            // Pengajuan (User, Maker, Approver)
            'view pengajuan',      // Termasuk Riwayat Aset, Pengajuan Barang
            'create pengajuan',    // Untuk Pinjaman, Pengembalian, Permintaan ATK
            'edit pengajuan',      // Edit/batalkan pengajuan sendiri (User/Maker)
            'delete pengajuan',    // Hapus pengajuan sendiri (User/Maker)
            
            // Approval (Approver only)
            'approve pengajuan',
            'reject pengajuan',

            // Laporan & Cetak (Maker & Approver)
            'view reports',
            'print qr'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // 2. Buat role: 'approver', 'maker', dan 'user'
        $approverRole = Role::firstOrCreate(['name' => 'approver']); 
        $makerRole = Role::firstOrCreate(['name' => 'maker']);      
        $userRole = Role::firstOrCreate(['name' => 'user']);        

        // 3. Berikan Permission ke ROLE (SAMA SEPERTI SEBELUMNYA)
        
        // Approver: Full Akses
        $approverRole->givePermissionTo(Permission::all());

        // Maker: Semua kecuali approve/reject pengajuan
        $makerPermissions = array_diff($permissions, [
            'approve pengajuan', 
            'reject pengajuan',
        ]);
        $makerRole->givePermissionTo($makerPermissions);
        
        // User: Hanya bisa lihat aset, dan mengelola pengajuan/permintaan sendiri
        $userRole->givePermissionTo([
            'view asets',
            'view pengajuan',
            'create pengajuan',
            'edit pengajuan',
            'delete pengajuan',
        ]);
        

        // 4. Buat user 'approver' dan 'maker'
        $approverUser = User::firstOrCreate(
            ['email' => 'approver@gmail.com'],
            [
                'name' => 'Approver MI',
                // 'lokasi' kolom dihapus
                'password' => Hash::make('1'),
            ]
        );
        $approverUser->assignRole('approver');

        $makerUser = User::firstOrCreate(
            ['email' => 'maker@gmail.com'],
            [
                'name' => 'Maker MI',
                // 'lokasi' kolom dihapus
                'password' => Hash::make('1'),
            ]
        );
        $makerUser->assignRole('maker');

        // 5. Buat user testing untuk setiap unit kerja (Role 'user')
        $testUsers = [];
        foreach ($unitKerja as $unit) {
            $email = strtolower(str_replace([' ', '&'], ['', '_'], $unit)) . "@unit.com";
            
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => 'User ' . $unit,
                    // 'lokasi' kolom dihapus
                    'password' => Hash::make('1'),
                ]
            );
            $user->assignRole('user');
            $testUsers[] = $user;
        }
        
        // Gabungkan semua user untuk factory pengajuan
        $allUsers = collect([$makerUser, $approverUser])->merge($testUsers);

        // 6. Buat 100 data aset dengan factory
        Aset::factory()->count(100)->create();
        $this->command->info('100 data Aset berhasil dibuat.');
        
        // 7. Buat 20 data pengajuan pinjaman secara acak
        $asetList = Aset::all();

        for ($i = 0; $i < 20; $i++) {
            $aset = $asetList->random();
            $userPeminjam = $allUsers->random();

            // KARENA 'maker_id' dan 'tanggal_approval' tidak ada di tabel, 
            // semua data dummy diatur ke status 'diajukan'
            $status = 'diajukan'; 

            PengajuanPinjaman::create([
                'aset_id' => $aset->id,
                'user_id' => $userPeminjam->id,
                'jumlah_pinjam' => rand(1, min(5, $aset->jumlah_barang)), 
                'status' => $status,
                // 'maker_id', 'tanggal_approval' dihapus dari insert
            ]);
        }
        $this->command->info('20 data Pengajuan Pinjaman berhasil dibuat.');


        $this->command->info('------------------------------------');
        $this->command->info('Database berhasil di-seed!');
        $this->command->info('Role dan Permission berhasil dibuat!');
        $this->command->info('------------------------------------');
        $this->command->info('AKUN UTAMA (Password semua: 1):');
        $this->command->info('- Approver: approver@gmail.com (Role: approver)');
        $this->command->info('- Maker: maker@gmail.com (Role: maker)');
        $this->command->info('------------------------------------');
        $this->command->info('AKUN USER UNIT KERJA (Password semua: 1):');
        foreach ($unitKerja as $unit) {
            $email = strtolower(str_replace([' ', '&'], ['', '_'], $unit)) . "@unit.com";
            $this->command->info('- User ' . $unit . ': ' . $email);
        }
    }
}
