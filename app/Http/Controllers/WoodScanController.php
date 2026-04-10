<?php

namespace App\Http\Controllers;

use App\Services\Wood\ImageDecoderService;
use App\Services\Wood\WoodBrainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Module 8 — Wood Scan Controller (HTTP Logic Gate)
 *
 * Handles ALL scan requests — React mobile (Base64) and Blade/web (FormData).
 * The controller is intentionally thin: decode → scan → respond.
 * All intelligence lives in WoodBrainService (Singleton).
 *
 * Endpoints:
 *   POST /api/scan/base64   → React mobile (raw Base64 or data URI)
 *   POST /api/scan/upload   → Blade web form (multipart FormData)
 *   POST /api/scan/{id}/verify → Human-in-the-Loop confirmation
 *   GET  /api/scan/{id}     → Get a single scan result
 *   GET  /api/cache/stats   → Cache analytics (top scanned species)
 */
class WoodScanController extends Controller
{
    public function __construct(
        private readonly WoodBrainService    $brain,
        private readonly ImageDecoderService $decoder,
    ) {}

    // ──────────────────────────────────────────────────
    // REACT MOBILE — Base64 Upload
    // ──────────────────────────────────────────────────

    /**
     * Accept a raw Base64 image from React mobile.
     *
     * Body (JSON):
     * {
     *   "image": "data:image/jpeg;base64,/9j/4AAQ...",  // or raw base64
     *   "weight": "hardwood",   // optional
     *   "smell": ["aromatic"],  // optional array
     *   "lat": 14.5995,         // optional GPS
     *   "lng": 120.9842
     * }
     */
    public function scanBase64(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image'  => ['required', 'string', 'min:100'],
            'weight' => ['nullable', 'in:hardwood,softwood'],
            'smell'  => ['nullable', 'array'],
            'smell.*' => ['string', 'in:aromatic,resinous,sweet,bitter,musty,odorless,spicy,sour'],
            'lat'    => ['nullable', 'numeric', 'between:-90,90'],
            'lng'    => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $imagePath = null;

        try {
            // Decode Base64 → temp file
            $imagePath = $this->decoder->decodeBase64($request->input('image'));

            // Run the Brain-First pipeline
            $result = $this->brain->identify(
                imagePath: $imagePath,
                userId:    $request->user()?->id,
                options:   $request->only(['weight', 'smell', 'lat', 'lng'])
            );

            return response()->json($result, $result['success'] ? 200 : 422);

        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 422);

        } finally {
            // Always clean up the temp file
            if ($imagePath) {
                $this->decoder->cleanup($imagePath);
            }
        }
    }

    // ──────────────────────────────────────────────────
    // BLADE / WEB — FormData Upload
    // ──────────────────────────────────────────────────

    /**
     * Accept a multipart file upload from the Blade web form.
     *
     * Supports both API (JSON response) and web (redirect with session flash).
     */
    public function scanUpload(Request $request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'image'  => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:10240'],
            'weight' => ['nullable', 'in:hardwood,softwood'],
            'smell'  => ['nullable', 'array'],
            'smell.*' => ['string', 'in:aromatic,resinous,sweet,bitter,musty,odorless,spicy,sour'],
            'lat'    => ['nullable', 'numeric'],
            'lng'    => ['nullable', 'numeric'],
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $imagePath = null;

        try {
            $imagePath = $this->decoder->decodeUploadedFile($request->file('image'));

            $result = $this->brain->identify(
                imagePath: $imagePath,
                userId:    $request->user()?->id,
                options:   $request->only(['weight', 'smell', 'lat', 'lng'])
            );

            if ($request->expectsJson()) {
                return response()->json($result, $result['success'] ? 200 : 422);
            }

            // Blade redirect with flash data for the result view
            return redirect()->route('scan.result')
                ->with('scan_result', $result);

        } catch (\RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage())->withInput();

        } finally {
            if ($imagePath) {
                $this->decoder->cleanup($imagePath);
            }
        }
    }

    // ──────────────────────────────────────────────────
    // Human-in-the-Loop — Verification Endpoint
    // ──────────────────────────────────────────────────

    /**
     * User verifies or corrects a scan result.
     * This hardens the cache for future scans of the same wood type.
     *
     * Body (JSON):
     * {
     *   "confirmed_species_id": 3
     * }
     */
    public function verify(Request $request, int $scanId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'confirmed_species_id' => ['required', 'integer', 'exists:wood_species,id'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $userId = $request->user()?->id;

        if (!$userId) {
            return response()->json(['success' => false, 'error' => 'Authentication required to verify results.'], 401);
        }

        $result = $this->brain->verifyResult(
            scanId:             $scanId,
            userId:             $userId,
            confirmedSpeciesId: (int)$request->input('confirmed_species_id')
        );

        return response()->json($result, $result['success'] ? 200 : 404);
    }

    // ──────────────────────────────────────────────────
    // Utilities
    // ──────────────────────────────────────────────────

    /** Get a single scan result by ID */
    public function show(int $id): JsonResponse
    {
        $scan = \App\Models\WoodScan::with(['finalSpecies', 'ciede2000Match', 'visionMatch', 'user'])
            ->find($id);

        if (!$scan) {
            return response()->json(['success' => false, 'error' => 'Scan not found'], 404);
        }

        return response()->json([
            'success' => true,
            'scan'    => $scan,
        ]);
    }

    /** Cache analytics — top scanned species (for DENR/research dashboard) */
    public function cacheStats(): JsonResponse
    {
        $stats = app(\App\Services\Wood\CacheMatcherService::class)->topScannedSpecies(10);

        return response()->json([
            'success'        => true,
            'top_species'    => $stats,
            'cache_total'    => \App\Models\WoodScanCache::count(),
            'verified_total' => \App\Models\WoodScanCache::where('user_verified', true)->count(),
        ]);
    }
}
