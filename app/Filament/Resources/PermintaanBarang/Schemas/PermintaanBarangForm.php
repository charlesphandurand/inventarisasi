<?php

namespace App\Filament\Resources\PermintaanBarang\Schemas;

use App\Models\Aset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput\Actions\Action;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model; // Import Model untuk tipe hinting di closure

class PermintaanBarangForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // Pilih Aset (ATK) - maker/approver dapat ubah saat edit; user hanya saat status 'diajukan'
                Select::make('aset_id')
                    ->label('Nama Barang (ATK)')
                    ->options(
                        \App\Models\Aset::query()
                            ->whereNotNull('nama_barang')
                            ->where('is_atk', 1)
                            ->get()
                            ->mapWithKeys(function ($aset) {
                                $lokasi = $aset->lokasi ?: 'Tanpa Lokasi';
                                return [$aset->id => "{$aset->nama_barang} - {$lokasi} (Stok: {$aset->jumlah_barang})"];
                            })
                    )
                    ->required()
                    ->searchable()
                    ->reactive()
                    ->disabled(function ($livewire, Get $get) {
                        $user = Auth::user();
                        $isEdit = $livewire instanceof EditRecord;
                        if (!$isEdit) return false; // saat create selalu aktif
                        $status = $get('status') ?? ($livewire->record->status ?? null);
                        $isMaker = $user?->hasRole('maker');
                        $isApprover = $user?->hasRole('approver');
                        $isAdmin = $isMaker || $isApprover;
                        if ($isAdmin) return false; // maker/approver bisa ubah saat edit
                        // user hanya bisa ubah jika status diajukan
                        return $status !== 'diajukan';
                    }),
                Select::make('status')
                ->label('Status Persetujuan')
                ->options(function (?Model $record) { 
                    $user = Auth::user();
                    $isMaker = $user?->hasRole('maker');
                    $isApprover = $user?->hasRole('approver');
                    $currentStatus = $record ? $record->status : 'diajukan'; // Default diajukan

                    $options = [];

                    // 1. Tampilkan status saat ini agar form tidak error
                    // Memberi label yang rapi (misalnya: Diajukan, Diverifikasi, Ditolak, Dikeluarkan)
                    $options[$currentStatus] = ucfirst(str_replace('_', ' ', $currentStatus));

                    // 2. Tentukan opsi yang bisa dipilih berdasarkan PERAN dan STATUS SAAT INI
                    
                    if ($isMaker && in_array($currentStatus, ['diajukan', 'diverifikasi'])) {
                        // Maker hanya boleh memilih antara 'diajukan' dan 'diverifikasi' (tidak ada 'ditolak' via edit)
                        $options['diajukan'] = 'Diajukan';
                        $options['diverifikasi'] = 'Diverifikasi Maker';
                        $options['ditolak'] = 'Ditolak';
                    }
                    
                    if ($isApprover && $currentStatus === 'diverifikasi') {
                        // Approver hanya bisa mengubah dari 'diverifikasi' menjadi 'dikeluarkan' atau 'ditolak'
                        $options['dikeluarkan'] = 'Dikeluarkan'; 
                        $options['ditolak'] = 'Ditolak';
                    }

                    // 3. Jika status sudah final
                    if (in_array($currentStatus, ['dikeluarkan', 'ditolak'])) {
                        // Keistimewaan: Maker boleh mengedit penolakannya sendiri
                        if ($isMaker && $currentStatus === 'ditolak' && $record && ($record->admin_id === ($user?->id))) {
                            $options = [
                                'diajukan' => 'Diajukan',
                                'diverifikasi' => 'Diverifikasi Maker',
                                'ditolak' => 'Ditolak',
                            ];
                            $statusOrder = ['diajukan', 'diverifikasi', 'dikeluarkan', 'ditolak'];
                            uksort($options, function($a, $b) use ($statusOrder) {
                                $aIndex = array_search($a, $statusOrder);
                                $bIndex = array_search($b, $statusOrder);
                                return $aIndex <=> $bIndex;
                            });
                            return $options;
                        }
                        return $options;
                    }

                    // Mengurutkan opsi agar urutan terlihat logis (misal: Diajukan -> Diverifikasi -> Ditolak/Dikeluarkan)
                    $statusOrder = ['diajukan', 'diverifikasi', 'dikeluarkan', 'ditolak'];
                    uksort($options, function($a, $b) use ($statusOrder) {
                        $aIndex = array_search($a, $statusOrder);
                        $bIndex = array_search($b, $statusOrder);
                        return $aIndex <=> $bIndex;
                    });
                    
                    return $options;
                })
                ->default('diajukan')
                // Pastikan menggunakan namespace yang benar untuk EditRecord di Form Schema
                ->visible(fn ($livewire) => $livewire instanceof EditRecord && Auth::user()?->hasAnyRole(['maker', 'approver']))
                ->required()
                ->afterStateUpdated(function ($state, callable $set, Get $get) {
                    // Logika untuk mencegah approval jika stok kurang saat diubah menjadi 'dikeluarkan'
                    if ($state === 'dikeluarkan') {
                        $aset = Aset::find($get('aset_id'));
                        $jumlah = (int) $get('jumlah_pinjam');
                        $stok = (int) ($aset->jumlah_barang ?? 0);

                        if ($aset && $jumlah > $stok) {
                            Notification::make()
                                ->title('Gagal Disetujui')
                                ->body("Stok saat ini hanya {$stok}. Jumlah permintaan ({$jumlah}) melebihi stok yang tersedia.")
                                ->danger()
                                ->send();
                            // Mengambil status SEBELUM diklik 'dikeluarkan' (yaitu 'diverifikasi' jika alurnya benar)
                            // Kita perlu menambahkan Get ke dalam callback ini untuk mendapatkan status sebelumnya.
                            // Karena kita tidak bisa mengakses status lama di AfterStateUpdated, 
                            // kita asumsikan transisi yang gagal adalah dari 'diverifikasi'
                            $set('status', 'diverifikasi'); 
                        }
                    }
                }),

                TextInput::make('jumlah_pinjam')
                    ->label('Jumlah Permintaan')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->reactive()
                    ->rules([
                        function (Get $get) {
                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                $aset = Aset::find($get('aset_id'));
                                if (!$aset) {
                                    return;
                                }
                                $stok = (int) $aset->jumlah_barang;
                                $jumlahPinjam = (int) $value;

                                if ($jumlahPinjam > $stok) {
                                    $fail("Jumlah permintaan ($jumlahPinjam) melebihi stok tersedia ($stok).");
                                    
                                    Notification::make()
                                        ->title('Jumlah Melebihi Stok')
                                        ->body("Stok tersedia: {$stok}. Anda meminta {$jumlahPinjam}.")
                                        ->danger()
                                        ->send();
                                }
                            };
                        },
                    ])
                    // Kendali enable/disable qty seperti aset: admin bebas; user hanya saat diajukan
                    ->disabled(function ($livewire, Get $get) {
                        $user = Auth::user();
                        $isEdit = $livewire instanceof EditRecord;
                        if (!$isEdit) return false; // saat create selalu aktif
                        $status = $get('status') ?? ($livewire->record->status ?? null);
                        $isAdmin = $user?->hasAnyRole(['maker','approver']);
                        if ($isAdmin) return false;
                        return $status !== 'diajukan';
                    }),

                // Placeholder Informasi Stok dengan format Markdown yang lebih rapi
                Placeholder::make('live_stock_info')
                    ->label('Informasi Stok ATK Terpilih')
                    ->content(function (Get $get) {
                        $asetId = $get('aset_id');
                        $jumlahPinjam = (int) $get('jumlah_pinjam');
                        
                        if (!$asetId) {
                            return 'Pilih barang (ATK) untuk melihat detail stok dan lokasi.';
                        }
                        
                        $aset = Aset::find($asetId);
                        if (!$aset) {
                            return 'Aset tidak ditemukan.';
                        }
                        
                        $stokTersedia = (int) $aset->jumlah_barang;
                        $lokasi = $aset->lokasi ?: 'Tanpa Lokasi';
                        $sisaStok = $stokTersedia - $jumlahPinjam;
                        
                        $info = "**📦 {$aset->nama_barang} (ATK)**\n\n";
                        $info .= "- 📍 **Lokasi Penyimpanan:** {$lokasi}\n";
                        $info .= "- 📊 **Stok Tersedia:** {$stokTersedia} unit\n";
                        
                        if ($jumlahPinjam > 0) {
                            $status = $sisaStok >= 0 ? 'CUKUP' : 'TIDAK CUKUP';
                            $info .= "- 📋 **Sisa Setelah Diminta:** {$sisaStok} unit (**{$status}**)";
                        }
                        
                        return $info;
                    })
                    ->markdown()
                    ->visible(fn (Get $get) => $get('aset_id') !== null),
            ]);
    }
}
