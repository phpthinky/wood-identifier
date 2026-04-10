<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WoodScan;
use App\Models\WoodScanCache;
use App\Models\WoodSpecies;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'species_total'    => WoodSpecies::count(),
            'protected_total'  => WoodSpecies::where('is_protected', true)->count(),
            'scans_total'      => WoodScan::count(),
            'scans_today'      => WoodScan::whereDate('created_at', today())->count(),
            'cache_total'      => WoodScanCache::count(),
            'cache_verified'   => WoodScanCache::where('user_verified', true)->count(),
            'forensic_flags'   => WoodScan::where('forensic_flag', true)->count(),
            'ai_calls'         => WoodScan::where('engine_used', 'ai_vision')->count(),
        ];

        $topSpecies = WoodScanCache::with('matchedSpecies')
            ->whereNotNull('matched_species_id')
            ->selectRaw('matched_species_id, SUM(hit_count) as total_hits')
            ->groupBy('matched_species_id')
            ->orderByDesc('total_hits')
            ->limit(5)
            ->get();

        $recentScans = WoodScan::with('finalSpecies')
            ->latest()
            ->limit(8)
            ->get();

        return view('admin.dashboard', compact('stats', 'topSpecies', 'recentScans'));
    }

    public function scansIndex()
    {
        $scans = WoodScan::with(['finalSpecies', 'user'])
            ->latest()
            ->paginate(20);

        return view('admin.scans', compact('scans'));
    }

    public function cacheIndex()
    {
        $cache = WoodScanCache::with(['matchedSpecies', 'verifiedBy'])
            ->orderByDesc('hit_count')
            ->paginate(25);

        return view('admin.cache', compact('cache'));
    }
}
