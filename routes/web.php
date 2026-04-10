<?php

use App\Http\Controllers\WoodScanController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Blade/Livewire Fallback
|--------------------------------------------------------------------------
|
| These routes serve the Blade web interface — used as an admin portal
| and as a fallback when the React mobile app is unavailable.
|
*/

// Home — upload form
Route::get('/', function () {
    return view('wood.scan');
})->name('home');

// Blade form submission
Route::post('/scan', [WoodScanController::class, 'scanUpload'])
    ->name('scan.submit');

// Result view (receives flash data from redirect)
Route::get('/scan/result', function () {
    return view('wood.result');
})->name('scan.result');
