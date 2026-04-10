<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wood Anatomy — Scan</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, sans-serif; background: #1a1208; color: #f0e8d0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { background: #2a1f0e; border: 1px solid #5a3e1b; border-radius: 12px; padding: 2rem; width: 100%; max-width: 520px; box-shadow: 0 8px 32px rgba(0,0,0,.5); }
        h1 { font-size: 1.5rem; margin-bottom: 0.25rem; color: #d4a862; }
        .subtitle { color: #a0855a; font-size: 0.875rem; margin-bottom: 1.5rem; }
        label { display: block; font-size: 0.8rem; color: #a0855a; margin-bottom: 0.25rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; }
        .field { margin-bottom: 1rem; }
        input[type="file"] { width: 100%; padding: 0.75rem; background: #1a1208; border: 1px dashed #5a3e1b; border-radius: 8px; color: #f0e8d0; cursor: pointer; }
        select, input[type="text"] { width: 100%; padding: 0.6rem 0.75rem; background: #1a1208; border: 1px solid #5a3e1b; border-radius: 6px; color: #f0e8d0; font-size: 0.9rem; }
        .smell-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
        .smell-item { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; }
        .smell-item input[type="checkbox"] { accent-color: #d4a862; }
        .preview { width: 100%; max-height: 200px; object-fit: cover; border-radius: 8px; margin-top: 0.5rem; display: none; }
        .btn { width: 100%; padding: 0.75rem; background: #8b5a1f; color: #f0e8d0; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; margin-top: 1rem; transition: background 0.2s; }
        .btn:hover { background: #a0692a; }
        .alert { padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.875rem; }
        .alert-error { background: rgba(180,40,40,.2); border: 1px solid rgba(180,40,40,.4); color: #f87171; }
        .brain-badge { display: inline-block; background: rgba(212,168,98,.1); border: 1px solid #d4a862; color: #d4a862; font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: 20px; margin-left: 0.5rem; vertical-align: middle; }
    </style>
</head>
<body>
<div class="card">
    <h1>Wood Identifier <span class="brain-badge">Brain-First AI</span></h1>
    <p class="subtitle">OpenCV Local Engine + Claude Vision Fallback</p>

    @if(session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-error">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form action="{{ route('scan.submit') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="field">
            <label>Wood Photo *</label>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp"
                   onchange="previewImage(this)" required>
            <img id="preview" class="preview" alt="Preview">
        </div>

        <div class="field">
            <label>Weight (Optional)</label>
            <select name="weight">
                <option value="">— Not selected —</option>
                <option value="hardwood">Hardwood (heavy, dense)</option>
                <option value="softwood">Softwood (light, floats)</option>
            </select>
        </div>

        <div class="field">
            <label>Smell Profile (Optional — Expert Mode)</label>
            <div class="smell-grid">
                @foreach(['aromatic' => 'Aromatic/Fragrant', 'resinous' => 'Resinous/Piney', 'sweet' => 'Sweet/Vanilla', 'bitter' => 'Bitter/Astringent', 'musty' => 'Musty/Earthy', 'odorless' => 'Odorless', 'spicy' => 'Spicy/Pepper', 'sour' => 'Sour/Acidic'] as $val => $label)
                    <label class="smell-item">
                        <input type="checkbox" name="smell[]" value="{{ $val }}">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">
            <div class="field">
                <label>Latitude (GPS)</label>
                <input type="text" name="lat" placeholder="14.5995" inputmode="decimal">
            </div>
            <div class="field">
                <label>Longitude (GPS)</label>
                <input type="text" name="lng" placeholder="120.9842" inputmode="decimal">
            </div>
        </div>

        <button type="submit" class="btn">Identify Wood</button>
    </form>
</div>

<script>
function previewImage(input) {
    const preview = document.getElementById('preview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>
