<?php

use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('home');
// });

Route::get('/', function () {
    // Cek apakah pengguna sudah login
    if (auth()->check()) {
        // Jika sudah login, arahkan ke dashboard Filament
        // Default URL dashboard Filament biasanya '/admin'
        return redirect()->route('filament.admin.pages.dashboard');
    }

    // Jika belum login, arahkan ke halaman login
    // Default URL login Filament biasanya '/admin/login'
    return redirect()->route('filament.admin.auth.login');
});
