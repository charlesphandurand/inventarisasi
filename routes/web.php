<?php

use Illuminate\Support\Facades\Route;
use App\Models\Aset;

Route::get('/', function () {
    return view('home');
});
