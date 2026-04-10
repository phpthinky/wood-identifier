@extends('admin.layout')
@section('title', 'Brain Cache')
@section('page-title', 'Brain Cache')

@section('content')

<div class="card">
    <div class="card-header">
        <h2>⚡ Wood Scan Cache — Self-Learning Memory ({{ $cache->total() }} entries)</h2>
    </div>
    <div style="padding:0; overflow-x:auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Species</th>
                    <th>Fingerprint</th>
                    <th>Color</th>
                    <th>Engine</th>
                    <th>Confidence</th>
                    <th>Hits</th>
                    <th>Verified</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cache as $entry)
                <tr>
                    <td style="font-weight:600">{{ $entry->matchedSpecies?->name ?? '—' }}</td>
                    <td style="font-size:0.78rem; color:var(--muted)">
                        {{ str_replace('_',' ', $entry->wood_type ?? '—') }} ·
                        {{ str_replace('_',' ', $entry->grain_pattern ?? '—') }} ·
                        {{ str_replace('_',' ', $entry->pore_type ?? '—') }}
                    </td>
                    <td>
                        @if($entry->color_hex)
                            <span class="swatch" style="background:{{ $entry->color_hex }}"></span>
                            <span style="font-size:0.8rem; font-family:monospace">{{ $entry->color_hex }}</span>
                        @else —
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $entry->engine_used === 'ai_vision' ? 'badge-blue' : 'badge-gold' }}">
                            {{ str_replace('_',' ', $entry->engine_used) }}
                        </span>
                    </td>
                    <td>
                        <span class="badge {{ match($entry->confidence_level) { 'very_high','high'=>'badge-green','medium'=>'badge-gold',default=>'badge-red' } }}">
                            {{ number_format($entry->confidence_score, 0) }}%
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-gold">{{ number_format($entry->hit_count) }}</span>
                    </td>
                    <td>
                        @if($entry->user_verified)
                            <span class="badge badge-green" title="By {{ $entry->verifiedBy?->name }}">✓ Verified</span>
                        @else
                            <span style="color:var(--muted); font-size:0.8rem">Unverified</span>
                        @endif
                    </td>
                    <td style="font-size:0.75rem; color:var(--muted)">{{ $entry->created_at?->format('M d, Y') }}</td>
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center; color:var(--muted); padding:1.5rem">Cache is empty. Run some scans first.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($cache->hasPages())
    <div style="padding:1rem 1.25rem; border-top:1px solid var(--border)">
        {{ $cache->links() }}
    </div>
    @endif
</div>
@endsection
