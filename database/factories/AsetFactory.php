<?php

namespace Database\Factories;

use App\Filament\Resources\Asets\AsetResource; // Wajib diimpor
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Aset>
 */
class AsetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $lokasiList = [
            'Gudang ATK Lt. 1',
            'Gudang ATK Lt. 5',
            'Gudang Lt. 3',
            'Gudang Lt. 4',
            'Gudang Lt. 5',
            'Gudang Lt. 6',
            'Gudang RBI Kayu Tangi',
            'Gudang RBI Bumi Asih',
        ];
        
        $vendorList = [
            'PT. Sinar Jaya',
            'CV. Makmur Sentosa',
            'PT. Global Cemerlang',
            'UD. Mandiri Abadi',
            'Toko Berkah Bersama',
        ];

        // Tentukan apakah ini ATK atau bukan secara acak
        $isAtk = $this->faker->boolean(40); // 40% chance of being ATK
        $kondisiList = ['Baik', 'Kurang Baik', 'Rusak'];
        
        // Daftar nama barang ATK yang lebih spesifik untuk simulasi data
        $atkItems = [
            'Pulpen Gel Hitam', 'Kertas HVS A4 80gr', 'Stapler Besar', 
            'Map Plastik Biru', 'Binder Clip Kecil', 'Post-it Kuning',
            'Tinta Printer Canon 810', 'Penghapus Karet', 'Penggaris Besi 30cm',
        ];
        
        // --- LOGIKA PERBAIKAN UNTUK MENGHINDARI OverflowException ---
        $namaBarang = $isAtk 
            // FIX: Hapus unique() pada randomElement($atkItems) karena daftar terbatas.
            // Kombinasi $atkItems (tidak unik) + unique()->randomNumber(3) (unik) akan menghasilkan nama yang unik.
            ? $this->faker->randomElement($atkItems) . ' ' . $this->faker->unique()->randomNumber(3) 
            // Untuk non-ATK, biarkan unique()->words(4, true) karena pool katanya sangat besar.
            : $this->faker->unique()->words(4, true); 

        return [
            // Pastikan nama barang selalu unik
            'nama_barang' => $namaBarang, 
            'jumlah_barang' => $this->faker->numberBetween(1, 100),
            'lokasi' => $this->faker->randomElement($lokasiList),
            // Pastikan keterangan selalu unik
            'keterangan' => $this->faker->unique()->sentence(6, true), 
            'nama_vendor' => $this->faker->randomElement($vendorList),
            'harga' => $this->faker->numberBetween(100000, 10000000),
            
            // Tambahan Baru:
            'is_atk' => $isAtk,
            'expired_date' => $isAtk ? $this->faker->optional(0.7)->dateTimeBetween('now', '+2 years') : null, // Hanya ATK yang mungkin punya Expired Date
            'kondisi_barang' => $this->faker->randomElement($kondisiList),
            'qr_code' => null, // Dibuat null, karena akan diisi setelah record dibuat (via afterCreating)
            'qr_options' => [], // Kosongkan
        ];
    }
    
    /**
     * Configure the model factory.
     * Logika: Setelah aset dibuat dan memiliki ID, update kolom qr_code
     * dengan URL view Filament yang benar.
     *
     * @return $this
     */
    public function configure(): static
    {
        return $this->afterCreating(function (\App\Models\Aset $aset) {
            try {
                // Menggunakan AsetResource untuk mendapatkan URL View aset yang baru dibuat.
                $viewUrl = AsetResource::getUrl('view', ['record' => $aset]);
                $aset->update(['qr_code' => $viewUrl]);
            } catch (\Exception $e) {
                // Jika terjadi kegagalan (misalnya rute Filament tidak dimuat), gunakan placeholder yang informatif.
                $aset->update(['qr_code' => 'factory-url-failed-' . $aset->id]);
            }
        });
    }
}
