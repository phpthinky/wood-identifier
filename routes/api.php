<?php

use App\Http\Controllers\WoodScanController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Wood Anatomy API Routes
|--------------------------------------------------------------------------
|
| React Mobile (Base64) + External API clients
| All scan endpoints work with Sanctum token auth (optional for guests)
|
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// ──────────────────────────────────────────────────
// Public scan endpoints (guest allowed for field use)
// ──────────────────────────────────────────────────
Route::prefix('scan')->group(function () {

    // React mobile: raw Base64 or data URI
    Route::post('/base64', [WoodScanController::class, 'scanBase64'])
        ->name('api.scan.base64');

    // React mobile / API: multipart FormData
    Route::post('/upload', [WoodScanController::class, 'scanUpload'])
        ->name('api.scan.upload');

    // Get a scan result by ID
    Route::get('/{id}', [WoodScanController::class, 'show'])
        ->name('api.scan.show')
        ->where('id', '[0-9]+');

    // Human-in-the-Loop: verify/correct a result (auth required)
    Route::post('/{id}/verify', [WoodScanController::class, 'verify'])
        ->name('api.scan.verify')
        ->middleware('auth:sanctum')
        ->where('id', '[0-9]+');
});

// ──────────────────────────────────────────────────
// Analytics & Admin
// ──────────────────────────────────────────────────
Route::prefix('cache')->group(function () {
    Route::get('/stats', [WoodScanController::class, 'cacheStats'])
        ->name('api.cache.stats');
});
