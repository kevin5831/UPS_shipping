<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UpsController;

Route::get('/', function () {
    return view('welcome');
});

// UPS Shipping Routes
Route::prefix('shipment')->name('shipment.')->group(function () {
    Route::get('/form', [UpsController::class, 'showForm'])->name('form');
    Route::post('/rate', [UpsController::class, 'getRate'])->name('rate');
    Route::post('/create', [UpsController::class, 'createShipment'])->name('create');
    Route::get('/test-auth', [UpsController::class, 'testAuthentication'])->name('test-auth');
});

// Route::redirect('/', '/shipment/form');