<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Password reset route required for notifications
Route::get('password/reset/{token}', function ($token) {
    return 'Password reset page (token: ' . $token . ')';
})->name('password.reset');
