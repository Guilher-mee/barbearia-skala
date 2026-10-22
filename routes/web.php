<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::book-appointment')->name('home');

if (app()->environment(['local', 'testing'])) {
    Route::livewire('/agenda', 'pages::daily-agenda')->name('agenda');
}
