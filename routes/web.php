<?php

use App\Http\Controllers\LogoutController;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::book-appointment')->name('home');

Route::livewire('/entrar', 'pages::login')->middleware('guest')->name('login');
Route::post('/sair', LogoutController::class)->middleware('auth')->name('logout');

Route::middleware(['auth', 'can:access-staff-area'])->group(function (): void {
    Route::livewire('/agenda', 'pages::daily-agenda')->name('agenda');
    Route::livewire('/servicos', 'pages::manage-services')->name('services');
});
