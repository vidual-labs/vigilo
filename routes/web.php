<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\LocaleController;
use App\Livewire\Checks\CheckSettings;
use App\Livewire\Clients\ClientEdit;
use App\Livewire\Clients\ClientIndex;
use App\Livewire\Connections\ConnectionIndex;
use App\Livewire\Findings\FindingShow;
use App\Livewire\Findings\Inbox;
use App\Livewire\Notifications\NotificationSettings;
use App\Livewire\Profile;
use App\Livewire\Users\UserIndex;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::post('/locale', LocaleController::class)->name('locale');

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/profile', Profile::class)->name('profile');

    Route::middleware('password.changed')->group(function () {
        Route::redirect('/', '/findings');
        Route::get('/findings', Inbox::class)->name('findings.index');
        Route::get('/findings/{finding}', FindingShow::class)->name('findings.show');
        Route::get('/clients', ClientIndex::class)->name('clients.index');
        Route::get('/clients/create', ClientEdit::class)->middleware('can:operate')->name('clients.create');
        Route::get('/clients/{client}', ClientEdit::class)->name('clients.edit');
        Route::get('/connections', ConnectionIndex::class)->name('connections.index');
        Route::get('/checks', CheckSettings::class)->name('checks.index');
        Route::get('/notifications', NotificationSettings::class)->name('notifications.index');
        Route::get('/users', UserIndex::class)->middleware('can:operate')->name('users.index');
    });
});
