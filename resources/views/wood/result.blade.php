<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wood Scan Result</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, sans-serif; background: #1a1208; color: #f0e8d0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; }
        .card { background: #2a1f0e; border: 1px solid #5a3e1b; border-radius: 12px; padding: 2rem; width: 100%; max-width: 580px; box-shadow: 0 8px 32px rgba(0,0,0,.5); }
        h1 { font-size: 1.4rem; color: #d4a862; margin-bottom: 1rem; }
        .result-species { font-size: 2rem; font-weight: 700; color: #f0e8d0; margin-bottom: 0.25rem; }
        .result-meta { color: #a0855a; font-size: 0.875rem; margin-bottom: 1.5rem; }
        .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; margin-right: 0.25rem; }
        .badge-vhigh  { background: rgba(34,197,94,.15); border: 1px solid rgba(34,197,94,.4); color: #4ade80; }
        .badge-high   { background: rgba(99,179,237,.15); border: 1px solid rgba(99,179,237,.4); color: #7dd3fc; }
        .badge-medium { background: rgba(251,191,36,.15); border: 1px solid rgba(251,191,36,.4); color: #fbbf24; }
        .badge-low    { background: rgba(251,113,133,.15); border: 1px solid rgba(251,113,133,.4); color: #f87171; }
        .badge-flag   { background: rgba(220,38,38,.2); border: 1px solid rgba(220,38,38,.5); color: #f87171; }
        .badge-cache  { background: rgba(212,168,98,.1); border: 1px solid #d4a862; color: #d4a862; }
        .badge-ai     { background: rgba(168,85,247,.1); border: 1px solid rgba(168,85,247,.5); color: #c084fc; }
        .section { margin-bottom: 1.25rem; }
        .section-title { font-size: 0.7rem; color: #a0855a; text-transform: uppercase; letter-spacing: .1em; font-weight: 700; margin-bottom: 0.5rem; }
        .feature-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
        .feature { background: #1a1208; border: 1px solid #3a2910; border-radius: 6px; padding: 0.5rem 0.75rem; }
        .feature-label { font-size: 0.7rem; color: #a0855a; }
        .feature-value { font-size: 0.9rem; color: #f0e8d0; font-weight: 600; }
        .color-swatch { display: inline-block; width: 16px; height: 16px; border-radius: 3px; vertical-align: middle; margin-right: 0.25rem; border: 1px solid rgba(255,255,255,.2); }
        .forensic-alert { background: rgba(220,38,38,.15); border: 1px solid rgba(220,38,38,.4); border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1rem; color: #f87171; }
        .top3 { font-size: 0.8rem; }
        .top3-row { display: flex; justify-content: space-between; padding: 0.35rem 0; border-bottom: 1px solid rgba(90,62,27,.3); }
        .confidence-bar { background: #1a1208; border-radius: 20px; height: 8px; margin-top: 0.5rem; overflow: hidden; }
        .confidence-fill { height: 100%; border-radius: 20px; background: linear-gradient(90deg, #8b5a1f, #d4a862); }
        .btn { display: inline-block; padding: 0.6rem 1.25rem; background: #8b5a1f; color: #f0e8d0; border: none; border-radius: 8px; font-size: 0.875rem; font-weight: 600; cursor: pointer; text-decoration: none; margin-right: 0.5rem; margin-top: 1rem; }
        .btn-outline { background: transparent; border: 1px solid #5a3e1b; }
    </style>
</head>
<body>
<div class="card">

@php $r = session('scan_result'); @endphp
@if(!$r)
    <p>No scan result found. <a href="{{ route('home') }}" style="color:#d4a862">Scan again</a></p>
@else

    <h1>Identification Result</h1>

    {{-- Forensic Alert --}}
    @if($r['forensic_flag'] ?? false)
    <div class="forensic-alert">
        <strong>⚠ Forensic Flag</strong>: {{ $r['forensic_reason'] ?? 'Suspicious material detected.' }}
    </div>
    @endif

    {{-- Species Result --}}
    <div class="section">
        <div class="result-species">{{ $r['species_name'] ?? 'Unknown' }}</div>
        <div class="result-meta">
            @php
                $levelClass = match($r['confidence_level'] ?? 'low') {
                    'very_high' => 'badge-vhigh',
                    'high'      => 'badge-high',
                    'medium'    => 'badge-medium',
                    default     => 'badge-low',
                };
                $sourceClass = match($r['source'] ?? 'opencv') {
                    'cache'    => 'badge-cache',
                    'ai_vision' => 'badge-ai',
                    default    => 'badge',
                };
            @endphp
            <span class="badge {{ $levelClass }}">{{ strtoupper(str_replace('_', ' ', $r['confidence_level'] ?? 'low')) }}</span>
            <span class="badge {{ $sourceClass }}">
                @if(($r['source'] ?? '') === 'cache') Cache Hit
                @elseif(($r['source'] ?? '') === 'ai_vision') Claude Vision
                @else OpenCV
                @endif
            </span>
            @if($r['forensic_flag'] ?? false)
                <span class="badge badge-flag">Flagged</span>
            @endif
        </div>
        <div class="confidence-bar">
            <div class="confidence-fill" style="width:{{ min(100, $r['confidence_score'] ?? 0) }}%"></div>
        </div>
        <div style="font-size:0.75rem; color:#a0855a; margin-top:0.25rem;">
            Confidence: {{ number_format($r['confidence_score'] ?? 0, 1) }}% &bull;
            Recommendation: <strong>{{ strtoupper($r['recommendation'] ?? 'verify') }}</strong>
        </div>
    </div>

    {{-- OpenCV Features (educational) --}}
    <div class="section">
        <div class="section-title">OpenCV Feature Fingerprint</div>
        <div class="feature-grid">
            <div class="feature">
                <div class="feature-label">Cut Type</div>
                <div class="feature-value">{{ str_replace('_', ' ', $r['wood_type'] ?? '—') }}</div>
            </div>
            <div class="feature">
                <div class="feature-label">Grain Pattern</div>
                <div class="feature-value">{{ str_replace('_', ' ', $r['grain_pattern'] ?? '—') }}</div>
            </div>
            <div class="feature">
                <div class="feature-label">Pore Structure</div>
                <div class="feature-value">{{ str_replace('_', ' ', $r['pore_type'] ?? '—') }}</div>
            </div>
            <div class="feature">
                <div class="feature-label">Surface Texture</div>
                <div class="feature-value">{{ $r['surface_texture'] ?? '—' }}</div>
            </div>
            <div class="feature">
                <div class="feature-label">Dominant Color</div>
                <div class="feature-value">
                    @if($r['color_hex'] ?? null)
                        <span class="color-swatch" style="background:{{ $r['color_hex'] }}"></span>
                    @endif
                    {{ $r['color_hex'] ?? '—' }}
                </div>
            </div>
            <div class="feature">
                <div class="feature-label">Photo Quality</div>
                <div class="feature-value">{{ ucfirst($r['photo_quality'] ?? '—') }}</div>
            </div>
        </div>
    </div>

    {{-- CIEDE2000 Top 3 --}}
    @if(!empty($r['ciede2000_top3']))
    <div class="section">
        <div class="section-title">CIEDE2000 Color Match Top 3</div>
        <div class="top3">
            @foreach($r['ciede2000_top3'] as $match)
            <div class="top3-row">
                <span>{{ $match['species'] ?? '?' }}</span>
                <span style="color:#a0855a">ΔE {{ number_format($match['delta_e'] ?? 0, 2) }} &bull; {{ $match['confidence'] ?? '' }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Advice --}}
    @if($r['photo_advice'] ?? null)
    <div class="section">
        <div class="section-title">Advice</div>
        <div style="font-size:0.875rem; color:#c0a870;">{{ $r['photo_advice'] }}</div>
    </div>
    @endif

    <a href="{{ route('home') }}" class="btn">Scan Another</a>
    <a href="#" class="btn btn-outline">Download Report</a>

@endif
</div>
</body>
</html>
