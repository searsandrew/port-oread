<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Volt::route('/login', 'auth.login')->name('login');
Volt::route('/register', 'auth.register')->name('register');

Volt::route('/profiles', 'profiles.index')->name('profiles.index');
Volt::route('/profiles/create', 'profiles.create')->name('profiles.create');

Route::middleware(['profile'])->group(function () {
    Volt::route('/dashboard', 'dashboard')->name('dashboard');
    Volt::route('/settings', 'settings.index')->name('settings.index');
    Volt::route('/profiles/switch', 'profiles.switch')->name('profiles.switch');
    Volt::route('/connect/disconnect', 'connect.disconnect')->name('connect.disconnect');

    Volt::route('/game', 'game.table')->name('game.table');
});

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');
