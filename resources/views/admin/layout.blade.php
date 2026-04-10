<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — Wood Identifier</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:        #0f0a04;
            --surface:   #1a1208;
            --surface2:  #241a0c;
            --border:    #3a2910;
            --border2:   #5a3e1b;
            --gold:      #d4a862;
            --gold-dim:  #a07840;
            --text:      #f0e8d0;
            --muted:     #a0855a;
            --danger:    #e05555;
            --success:   #4ade80;
            --info:      #7dd3fc;
            --warning:   #fbbf24;
        }

        body { font-family: system-ui, -apple-system, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; }

        /* ── Sidebar ── */
        .sidebar {
            width: 240px; min-height: 100vh; background: var(--surface);
            border-right: 1px solid var(--border); display: flex; flex-direction: column;
            position: fixed; top: 0; left: 0; z-index: 100;
        }
        .sidebar-brand {
            padding: 1.25rem 1.25rem 1rem;
            border-bottom: 1px solid var(--border);
        }
        .sidebar-brand h1 { font-size: 1rem; color: var(--gold); font-weight: 700; }
        .sidebar-brand p  { font-size: 0.7rem; color: var(--muted); margin-top: 0.1rem; }
        .sidebar-nav { padding: 0.75rem 0; flex: 1; overflow-y: auto; }
        .nav-section { padding: 0.5rem 1rem 0.25rem; font-size: 0.65rem; text-transform: uppercase; letter-spacing: .1em; color: var(--muted); font-weight: 700; }
        .nav-link {
            display: flex; align-items: center; gap: 0.6rem;
            padding: 0.55rem 1.25rem; font-size: 0.875rem; color: var(--muted);
            text-decoration: none; transition: all 0.15s; border-left: 3px solid transparent;
        }
        .nav-link:hover  { color: var(--text); background: var(--surface2); }
        .nav-link.active { color: var(--gold); background: rgba(212,168,98,.08); border-left-color: var(--gold); }
        .nav-link .icon  { width: 16px; text-align: center; font-size: 0.875rem; }
        .sidebar-footer { padding: 1rem 1.25rem; border-top: 1px solid var(--border); font-size: 0.75rem; color: var(--muted); }

        /* ── Main content ── */
        .main { margin-left: 240px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
        .topbar {
            height: 52px; background: var(--surface); border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 1.5rem; position: sticky; top: 0; z-index: 50;
        }
        .topbar-title { font-size: 0.95rem; font-weight: 600; color: var(--text); }
        .topbar-right  { display: flex; align-items: center; gap: 1rem; }
        .btn-scan {
            padding: 0.35rem 0.9rem; background: var(--gold-dim); color: var(--text);
            border: none; border-radius: 6px; font-size: 0.8rem; font-weight: 600;
            text-decoration: none; cursor: pointer; transition: background 0.2s;
        }
        .btn-scan:hover { background: var(--gold); }

        .content { padding: 1.5rem; flex: 1; }

        /* ── Cards ── */
        .card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 10px; overflow: hidden;
        }
        .card-header {
            padding: 0.9rem 1.25rem; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
        }
        .card-header h2 { font-size: 0.9rem; font-weight: 700; color: var(--text); }
        .card-body { padding: 1.25rem; }

        /* ── Stat cards ── */
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 1rem 1.25rem; }
        .stat-value { font-size: 1.75rem; font-weight: 700; color: var(--gold); }
        .stat-label { font-size: 0.75rem; color: var(--muted); margin-top: 0.2rem; }

        /* ── Tables ── */
        .table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        .table th { padding: 0.65rem 1rem; text-align: left; font-size: 0.7rem; text-transform: uppercase; letter-spacing: .07em; color: var(--muted); border-bottom: 1px solid var(--border); font-weight: 600; }
        .table td { padding: 0.75rem 1rem; border-bottom: 1px solid rgba(58,41,16,.5); vertical-align: middle; }
        .table tr:hover td { background: var(--surface2); }
        .table tr:last-child td { border-bottom: none; }

        /* ── Badges ── */
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .badge-green  { background: rgba(74,222,128,.12); border: 1px solid rgba(74,222,128,.3); color: #4ade80; }
        .badge-red    { background: rgba(224,85,85,.12);  border: 1px solid rgba(224,85,85,.3);  color: #f87171; }
        .badge-gold   { background: rgba(212,168,98,.12); border: 1px solid rgba(212,168,98,.3); color: var(--gold); }
        .badge-blue   { background: rgba(125,211,252,.12);border: 1px solid rgba(125,211,252,.3);color: #7dd3fc; }
        .badge-muted  { background: rgba(160,133,90,.12); border: 1px solid var(--border2);      color: var(--muted); }

        /* ── Forms ── */
        .form-group { margin-bottom: 1.1rem; }
        .form-label { display: block; font-size: 0.75rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 0.35rem; }
        .form-control {
            width: 100%; padding: 0.6rem 0.85rem; background: var(--bg);
            border: 1px solid var(--border2); border-radius: 7px; color: var(--text);
            font-size: 0.875rem; transition: border-color 0.15s;
        }
        .form-control:focus { outline: none; border-color: var(--gold); }
        .form-control::placeholder { color: var(--muted); }
        select.form-control option { background: var(--surface); }
        textarea.form-control { resize: vertical; min-height: 80px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
        .form-hint { font-size: 0.75rem; color: var(--muted); margin-top: 0.25rem; }

        /* ── Buttons ── */
        .btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.55rem 1.1rem; border-radius: 7px; font-size: 0.875rem; font-weight: 600; cursor: pointer; text-decoration: none; border: none; transition: all 0.15s; }
        .btn-primary   { background: #7a4e1a; color: var(--text); }
        .btn-primary:hover { background: var(--gold-dim); }
        .btn-success   { background: rgba(74,222,128,.15); border: 1px solid rgba(74,222,128,.3); color: #4ade80; }
        .btn-success:hover { background: rgba(74,222,128,.25); }
        .btn-danger    { background: rgba(224,85,85,.15); border: 1px solid rgba(224,85,85,.3); color: #f87171; }
        .btn-danger:hover { background: rgba(224,85,85,.25); }
        .btn-outline   { background: transparent; border: 1px solid var(--border2); color: var(--muted); }
        .btn-outline:hover { color: var(--text); border-color: var(--gold); }
        .btn-sm { padding: 0.3rem 0.7rem; font-size: 0.78rem; }
        .btn-icon { padding: 0.35rem 0.5rem; }

        /* ── Alerts ── */
        .alert { padding: 0.75rem 1rem; border-radius: 7px; margin-bottom: 1rem; font-size: 0.875rem; }
        .alert-success { background: rgba(74,222,128,.1);  border: 1px solid rgba(74,222,128,.3); color: #4ade80; }
        .alert-danger  { background: rgba(224,85,85,.1);   border: 1px solid rgba(224,85,85,.3);  color: #f87171; }
        .alert-warning { background: rgba(251,191,36,.1);  border: 1px solid rgba(251,191,36,.3); color: #fbbf24; }

        /* ── Color swatch ── */
        .swatch { display: inline-block; width: 18px; height: 18px; border-radius: 4px; vertical-align: middle; border: 1px solid rgba(255,255,255,.15); margin-right: 0.3rem; }

        /* ── Image grid ── */
        .img-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 0.75rem; }
        .img-thumb { position: relative; border-radius: 8px; overflow: hidden; border: 1px solid var(--border); aspect-ratio: 1; }
        .img-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .img-thumb-label { position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,.7); font-size: 0.65rem; padding: 0.2rem 0.4rem; color: var(--muted); }
        .img-thumb-del { position: absolute; top: 4px; right: 4px; background: rgba(0,0,0,.6); color: #f87171; border: none; border-radius: 4px; padding: 0.15rem 0.3rem; font-size: 0.7rem; cursor: pointer; }

        /* ── Divider ── */
        .divider { border: none; border-top: 1px solid var(--border); margin: 1.25rem 0; }

        /* ── Tabs ── */
        .tabs { display: flex; gap: 0; border-bottom: 1px solid var(--border); margin-bottom: 1.25rem; }
        .tab { padding: 0.65rem 1.1rem; font-size: 0.85rem; color: var(--muted); cursor: pointer; text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -1px; transition: all 0.15s; }
        .tab:hover  { color: var(--text); }
        .tab.active { color: var(--gold); border-bottom-color: var(--gold); font-weight: 600; }

        /* ── Protected badge ── */
        .protected-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: #f87171; margin-right: 0.3rem; }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main { margin-left: 0; }
            .form-row, .form-row-3 { grid-template-columns: 1fr; }
        }
    </style>
    @stack('styles')
</head>
<body>

{{-- Sidebar --}}
<aside class="sidebar">
    <div class="sidebar-brand">
        <h1>Wood Identifier</h1>
        <p>Admin Dashboard</p>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">Overview</div>
        <a href="{{ route('admin.dashboard') }}"
           class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <span class="icon">◈</span> Dashboard
        </a>

        <div class="nav-section">Species Library</div>
        <a href="{{ route('admin.species.index') }}"
           class="nav-link {{ request()->routeIs('admin.species.*') ? 'active' : '' }}">
            <span class="icon">⊞</span> All Species
        </a>
        <a href="{{ route('admin.species.create') }}"
           class="nav-link {{ request()->routeIs('admin.species.create') ? 'active' : '' }}">
            <span class="icon">＋</span> Add Species
        </a>

        <div class="nav-section">Scan Activity</div>
        <a href="{{ route('admin.scans.index') }}"
           class="nav-link {{ request()->routeIs('admin.scans.*') ? 'active' : '' }}">
            <span class="icon">◎</span> Scan History
        </a>
        <a href="{{ route('admin.cache.index') }}"
           class="nav-link {{ request()->routeIs('admin.cache.*') ? 'active' : '' }}">
            <span class="icon">⚡</span> Brain Cache
        </a>

        <div class="nav-section">Tools</div>
        <a href="{{ route('home') }}" class="nav-link">
            <span class="icon">⬆</span> Upload &amp; Scan
        </a>
    </nav>
    <div class="sidebar-footer">
        Brain-First AI · Laravel 13
    </div>
</aside>

{{-- Main --}}
<div class="main">
    <div class="topbar">
        <span class="topbar-title">@yield('page-title', 'Dashboard')</span>
        <div class="topbar-right">
            <a href="{{ route('home') }}" class="btn-scan">⬆ New Scan</a>
        </div>
    </div>

    <div class="content">
        {{-- Flash messages --}}
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
            </div>
        @endif

        @yield('content')
    </div>
</div>

@stack('scripts')
</body>
</html>
