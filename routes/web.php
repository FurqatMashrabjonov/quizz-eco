<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => Auth::check() ? redirect()->route('dashboard') : redirect()->route('login'))
    ->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', fn () => redirect(Auth::user()->role === 'admin' ? '/admin' : route('quiz')))
        ->name('dashboard');

    Route::livewire('quiz', 'pages::quiz')->name('quiz');
});

require __DIR__.'/settings.php';
