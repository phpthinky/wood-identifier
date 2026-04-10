@extends('admin.layout')
@section('title', 'Scan History')
@section('page-title', 'Scan History')

@section('content')
<div class="card">
    <div class="card-header">
        <h2>◎ All Scans ({{ $scans->total() }})</h2>
    </div>
    <div style="padding:0; overflow-x:auto">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Final Match</th>
                    <th>Source</th>
                    <th>Confidence</th>
                    <th>Cut Type</th>
                    <th>Forensic</th>
                    <th>Recommendation</th>
                    <th>User</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($scans as $scan)
                <tr>
                    <td style="color:var(--muted); font-size:0.8rem">#{{ $scan->id }}</td>
                    <td>
                        <span style="font-weight:600">{{ $scan->finalSpecies?->name ?? 'Unknown' }}</span>
                        @if($scan->ciede2000Match && $scan->ciede2000_top_match !== $scan->final_match)
                            <div style="font-size:0.7rem; color:var(--muted)">CIEDE2000: {{ $scan->ciede2000Match?->name }}</div>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ match($scan->engine_used) { 'ai_vision'=>'badge-blue','ciede2000'=>'badge-gold',default=>'badge-muted' } }}">
                            {{ str_replace('_',' ', $scan->engine_used ?? '—') }}
                        </span>
                    </td>
                    <td>
                        <span class="badge {{ match($scan->confidence_level) { 'very_high','high'=>'badge-green','medium'=>'badge-gold',default=>'badge-red' } }}">
                            {{ number_format($scan->confidence_score ?? 0, 0) }}%
                        </span>
                    </td>
                    <td style="font-size:0.8rem; color:var(--muted)">{{ str_replace('_',' ', $scan->wood_type_detected ?? '—') }}</td>
                    <td>
                        @if($scan->forensic_flag)
                            <span class="badge badge-red" title="{{ $scan->forensic_reason }}">⚠ Flag</span>
                        @else
                            <span style="color:var(--muted); font-size:0.8rem">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ match($scan->recommendation) { 'accept'=>'badge-green','flag'=>'badge-red','retake'=>'badge-red',default=>'badge-gold' } }}">
                            {{ ucfirst($scan->recommendation ?? '—') }}
                        </span>
                    </td>
                    <td style="font-size:0.8rem; color:var(--muted)">{{ $scan->user?->name ?? 'Guest' }}</td>
                    <td style="font-size:0.75rem; color:var(--muted)">{{ $scan->created_at?->format('M d, H:i') }}</td>
                </tr>
                @empty
                <tr><td colspan="9" style="text-align:center; color:var(--muted); padding:1.5rem">No scans yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($scans->hasPages())
    <div style="padding:1rem 1.25rem; border-top:1px solid var(--border)">
        {{ $scans->links() }}
    </div>
    @endif
</div>
@endsection
