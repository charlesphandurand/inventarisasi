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

        // 1. Buat permission yang diperlukan
        $permissions = [
            // User management
            'view users',
            'create users', 
            'edit users',
            'delete users',
            
            // Aset management
            'view asets',
            'create asets',
            'edit asets', 
            'delete asets',
            
            // Pengajuan management
            'view pengajuan',
            'create pengajuan',
            'edit pengajuan',
            'delete pengajuan',
            'approve pengajuan',
            'reject pengajuan'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // 2. Buat role 'admin' dan 'user'
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $userRole = Role::firstOrCreate(['name' => 'user']);

        // 3. Berikan semua permission kepada admin
        $adminRole->givePermissionTo($permissions);

        // 4. Berikan permission terbatas kepada user
        $userRole->givePermissionTo([
            'view asets',
            'view pengajuan',
            'create pengajuan',
            'edit pengajuan',
            'delete pengajuan'
        ]);

        // 5. Buat user 'admin' atau pastikan sudah ada
        $adminUser = User::where('email', 'admin@gmail.com')->first();
        if (!$adminUser) {
            $adminUser = User::create([
                'name' => 'Administrator',
                'email' => 'admin@gmail.com',
                'password' => Hash::make('admin123'),
            ]);
        } else {
            // Update password admin jika sudah ada
            $adminUser->update([
                'password' => Hash::make('admin123'),
            ]);
        }
        $adminUser->assignRole('admin');

        // 6. Buat beberapa user testing dengan password "1"
        $testUsers = [
            [
                'name' => 'Test User 1',
                'email' => 'user1@test.com',
                'password' => '1',
                'role' => 'user'
            ],
            [
                'name' => 'Test User 2', 
                'email' => 'user2@test.com',
                'password' => '1',
                'role' => 'user'
            ],
            [
                'name' => 'Test Admin',
                'email' => 'admin2@test.com', 
                'password' => 'admin123',
                'role' => 'admin'
            ]
        ];

        foreach ($testUsers as $userData) {
            $user = User::where('email', $userData['email'])->first();
            if (!$user) {
                $user = User::create([
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password' => Hash::make($userData['password']),
                ]);
            } else {
                // Update password user yang sudah ada
                $user->update([
                    'password' => Hash::make($userData['password']),
                ]);
            }
            
            // Assign role
            $user->assignRole($userData['role']);
        }

        // 7. Buat beberapa user factory dengan password "1"
        $regularUsers = User::factory()->count(5)->create();
        foreach ($regularUsers as $user) {
            $user->assignRole('user');
        }

        // 8. Buat data aset
        Aset::factory()->count(100)->create();

        // 9. Buat 20 data pengajuan pinjaman secara acak
        for ($i = 0; $i < 20; $i++) {
            $aset = Aset::inRandomOrder()->first();
            $statuses = ['diajukan', 'disetujui', 'ditolak'];
            $status = $statuses[array_rand($statuses)];

            $adminId = null;
            $tanggalApproval = null;

            if ($status === 'disetujui') {
                $adminId = $adminUser->id;
                $tanggalApproval = Carbon::now()->subDays(rand(1, 10));
            }

            PengajuanPinjaman::create([
                'aset_id' => $aset->id,
                'user_id' => $regularUsers->random()->id,
                'jumlah_pinjam' => rand(1, $aset->jumlah_barang),
                'admin_id' => $adminId,
                'status' => $status,
            ]);
        }

        $this->command->info('Database berhasil di-seed!');
        $this->command->info('Role dan Permission berhasil dibuat!');
        $this->command->info('Admin: admin@gmail.com / admin123');
        $this->command->info('User 1: user1@test.com / 1');
        $this->command->info('User 2: user2@test.com / 1');
        $this->command->info('Admin 2: admin2@test.com / admin123');
        $this->command->info('User Factory: password = 1 (5 user)');
    }
}