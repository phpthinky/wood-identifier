@extends('admin.layout')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')

{{-- Stat cards --}}
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-value">{{ $stats['species_total'] }}</div>
        <div class="stat-label">Species in Library</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color:#f87171">{{ $stats['protected_total'] }}</div>
        <div class="stat-label">Protected Species</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ $stats['scans_total'] }}</div>
        <div class="stat-label">Total Scans</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color:#7dd3fc">{{ $stats['scans_today'] }}</div>
        <div class="stat-label">Scans Today</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color:#4ade80">{{ $stats['cache_total'] }}</div>
        <div class="stat-label">Cache Entries</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color:#c084fc">{{ $stats['ai_calls'] }}</div>
        <div class="stat-label">AI Vision Calls</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color:#fbbf24">{{ $stats['forensic_flags'] }}</div>
        <div class="stat-label">Forensic Flags</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color:#d4a862">{{ $stats['cache_verified'] }}</div>
        <div class="stat-label">Human-Verified</div>
    </div>
</div>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.25rem;">

    {{-- Top scanned species --}}
    <div class="card">
        <div class="card-header">
            <h2>⚡ Top Scanned Species</h2>
            <a href="{{ route('admin.cache.index') }}" class="btn btn-sm btn-outline">View Cache</a>
        </div>
        <div class="card-body" style="padding:0">
            @forelse($topSpecies as $row)
            <div style="display:flex; align-items:center; justify-content:space-between; padding:0.7rem 1.25rem; border-bottom:1px solid rgba(58,41,16,.4);">
                <span style="font-size:0.9rem">{{ $row->matchedSpecies?->name ?? '—' }}</span>
                <span class="badge badge-gold">{{ number_format($row->total_hits) }} hits</span>
            </div>
            @empty
            <div style="padding:1.25rem; color:var(--muted); font-size:0.875rem;">No cache data yet.</div>
            @endforelse
        </div>
    </div>

    {{-- Recent scans --}}
    <div class="card">
        <div class="card-header">
            <h2>◎ Recent Scans</h2>
            <a href="{{ route('admin.scans.index') }}" class="btn btn-sm btn-outline">All Scans</a>
        </div>
        <div class="card-body" style="padding:0">
            @forelse($recentScans as $scan)
            <div style="display:flex; align-items:center; justify-content:space-between; padding:0.65rem 1.25rem; border-bottom:1px solid rgba(58,41,16,.4);">
                <div>
                    <div style="font-size:0.875rem">{{ $scan->finalSpecies?->name ?? 'Unknown' }}</div>
                    <div style="font-size:0.7rem; color:var(--muted)">{{ $scan->created_at?->diffForHumans() }}</div>
                </div>
                <div style="display:flex; gap:0.4rem; align-items:center;">
                    @if($scan->forensic_flag)
                        <span class="badge badge-red">⚠ Flag</span>
                    @endif
                    <span class="badge {{ match($scan->confidence_level) { 'very_high','high' => 'badge-green', 'medium' => 'badge-gold', default => 'badge-red' } }}">
                        {{ ucfirst(str_replace('_',' ',$scan->confidence_level ?? 'low')) }}
                    </span>
                </div>
            </div>
            @empty
            <div style="padding:1.25rem; color:var(--muted); font-size:0.875rem;">No scans yet.</div>
            @endforelse
        </div>
    </div>

</div>

<div style="margin-top:1.25rem;" class="card">
    <div class="card-header">
        <h2>⊞ Species Library</h2>
        <a href="{{ route('admin.species.create') }}" class="btn btn-primary btn-sm">＋ Add Species</a>
    </div>
    <div class="card-body" style="padding:0">
        @include('admin.partials.species-table', ['species' => \App\Models\WoodSpecies::withCount(['colorReferences','referenceImages'])->with('grainProfile')->orderBy('name')->limit(6)->get(), 'compact' => true])
        <div style="padding:0.75rem 1.25rem; border-top:1px solid var(--border);">
            <a href="{{ route('admin.species.index') }}" class="btn btn-outline btn-sm">View all species →</a>
        </div>
    </div>
</div>

@endsection
