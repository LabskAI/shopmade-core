<?php

use Illuminate\Support\Facades\Route;
use Plugins\CustomerNotes\Http\Controllers\CustomerNoteController;

Route::get('/', [CustomerNoteController::class, 'index'])->name('index');
Route::get('/customers/{customer}', [CustomerNoteController::class, 'customer'])->name('customer');
Route::post('/customers/{customer}', [CustomerNoteController::class, 'store'])->name('store');
