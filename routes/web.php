<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SpeciesController;
use App\Http\Controllers\WoodScanController;
use Illuminate\Support\Facades\Route;

// ── Public scan routes ─────────────────────────────────────────────
Route::get('/', fn() => view('wood.scan'))->name('home');
Route::post('/scan', [WoodScanController::class, 'scanUpload'])->name('scan.submit');
Route::get('/scan/result', fn() => view('wood.result'))->name('scan.result');

// ── Admin routes ───────────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->group(function () {

    // Dashboard
    Route::get('/',       [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/scans',  [DashboardController::class, 'scansIndex'])->name('scans.index');
    Route::get('/cache',  [DashboardController::class, 'cacheIndex'])->name('cache.index');

    // Species CRUD
    Route::resource('species', SpeciesController::class);

    // Color references (nested under species)
    Route::post('species/{species}/colors',          [SpeciesController::class, 'storeColor'])->name('species.colors.store');
    Route::delete('species/{species}/colors/{color}',[SpeciesController::class, 'destroyColor'])->name('species.colors.destroy');

    // Smell profiles
    Route::post('species/{species}/smells',          [SpeciesController::class, 'storeSmell'])->name('species.smells.store');
    Route::delete('species/{species}/smells/{smell}',[SpeciesController::class, 'destroySmell'])->name('species.smells.destroy');

    // Grain profile (upsert)
    Route::post('species/{species}/grain',           [SpeciesController::class, 'storeGrain'])->name('species.grain.store');

    // Reference images
    Route::post('species/{species}/images',                   [SpeciesController::class, 'storeImage'])->name('species.images.store');
    Route::delete('species/{species}/images/{image}',         [SpeciesController::class, 'destroyImage'])->name('species.images.destroy');
    Route::post('species/{species}/images/{image}/primary',   [SpeciesController::class, 'setPrimaryImage'])->name('species.images.primary');
});

