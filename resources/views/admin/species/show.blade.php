@extends('admin.layout')
@section('title', $species->name)
@section('page-title', $species->name)

@section('content')

{{-- Species header --}}
<div class="card" style="margin-bottom:1.25rem">
    <div class="card-body" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem">
        <div>
            <div style="font-size:1.4rem; font-weight:700; color:var(--gold)">
                @if($species->is_protected)<span class="protected-dot" title="DENR Protected" style="width:10px;height:10px"></span>@endif
                {{ $species->name }}
                @if($species->local_name) <span style="color:var(--muted); font-size:1rem; font-weight:400">({{ $species->local_name }})</span>@endif
            </div>
            @if($species->scientific_name)
            <div style="font-style:italic; color:var(--muted); font-size:0.9rem">{{ $species->scientific_name }}</div>
            @endif
            <div style="margin-top:0.5rem; display:flex; flex-wrap:wrap; gap:0.4rem">
                <span class="badge {{ $species->hardness === 'hardwood' ? 'badge-gold' : 'badge-blue' }}">{{ ucfirst($species->hardness) }}</span>
                @if($species->is_protected)<span class="badge badge-red">DENR Protected</span>@endif
                @if($species->cites_appendix)<span class="badge badge-gold">CITES {{ $species->cites_appendix }}</span>@endif
                @if($species->density_min || $species->density_max)
                    <span class="badge badge-muted">{{ $species->density_min }}–{{ $species->density_max }} kg/m³</span>
                @endif
            </div>
            @if($species->description)
            <div style="margin-top:0.6rem; font-size:0.85rem; color:var(--muted); max-width:600px">{{ $species->description }}</div>
            @endif
        </div>
        <div style="display:flex; gap:0.5rem; flex-shrink:0">
            <a href="{{ route('admin.species.edit', $species) }}" class="btn btn-outline btn-sm">✎ Edit Info</a>
            <a href="{{ route('admin.species.index') }}" class="btn btn-outline btn-sm">← All Species</a>
        </div>
    </div>
</div>

{{-- Anatomy completion checklist --}}
<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(140px,1fr)); gap:0.75rem; margin-bottom:1.25rem">
    @php
        $checks = [
            ['Color References', $species->colorReferences->count() > 0, $species->colorReferences->count() . ' added'],
            ['Smell Profiles',   $species->smellProfiles->count() > 0,   $species->smellProfiles->count() . ' added'],
            ['Grain Profile',    $species->grainProfile !== null,         $species->grainProfile ? 'Complete' : 'Missing'],
            ['Reference Images', $species->referenceImages->count() > 0, $species->referenceImages->count() . ' uploaded'],
        ];
    @endphp
    @foreach($checks as [$label, $done, $note])
    <div style="background:var(--surface); border:1px solid {{ $done ? 'rgba(74,222,128,.3)' : 'rgba(224,85,85,.3)' }}; border-radius:8px; padding:0.75rem 1rem">
        <div style="font-size:1rem">{{ $done ? '✓' : '✗' }}</div>
        <div style="font-size:0.8rem; font-weight:600; margin-top:0.2rem; color:{{ $done ? '#4ade80' : '#f87171' }}">{{ $label }}</div>
        <div style="font-size:0.7rem; color:var(--muted)">{{ $note }}</div>
    </div>
    @endforeach
</div>

{{-- Tabs --}}
<div class="tabs" id="anatomy-tabs">
    <a href="#colors"  class="tab active" onclick="switchTab(this,'colors')">🎨 Colors ({{ $species->colorReferences->count() }})</a>
    <a href="#smell"   class="tab"        onclick="switchTab(this,'smell')">🌿 Smell ({{ $species->smellProfiles->count() }})</a>
    <a href="#grain"   class="tab"        onclick="switchTab(this,'grain')">◈ Grain Profile</a>
    <a href="#images"  class="tab"        onclick="switchTab(this,'images')">🖼 Images ({{ $species->referenceImages->count() }})</a>
</div>

{{-- ══════════════════════ COLOR REFERENCES ══════════════════════ --}}
<div id="tab-colors">

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; align-items:start">

        {{-- Existing colors --}}
        <div class="card">
            <div class="card-header"><h2>Existing Color References</h2></div>
            <div class="card-body" style="padding:0">
                @forelse($species->colorReferences->sortBy('label') as $color)
                <div style="display:flex; align-items:center; justify-content:space-between; padding:0.7rem 1.1rem; border-bottom:1px solid rgba(58,41,16,.4)">
                    <div style="display:flex; align-items:center; gap:0.75rem">
                        <span class="swatch" style="background:{{ $color->hex }}; width:28px; height:28px; border-radius:6px"></span>
                        <div>
                            <div style="font-size:0.875rem; font-weight:600">{{ $color->hex }}</div>
                            <div style="font-size:0.75rem; color:var(--muted)">{{ ucfirst(str_replace('_',' ',$color->label)) }}</div>
                            @if($color->notes)<div style="font-size:0.7rem; color:var(--muted)">{{ $color->notes }}</div>@endif
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.species.colors.destroy', [$species, $color]) }}"
                          onsubmit="return confirm('Remove this color reference?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger btn-sm btn-icon" title="Delete">✕</button>
                    </form>
                </div>
                @empty
                <div style="padding:1.1rem; color:var(--muted); font-size:0.875rem">No color references yet.</div>
                @endforelse
            </div>
        </div>

        {{-- Add color form --}}
        <div class="card">
            <div class="card-header"><h2>＋ Add Color Reference</h2></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.species.colors.store', $species) }}">
                    @csrf
                    <div class="form-group">
                        <label class="form-label">Hex Color *</label>
                        <div style="display:flex; gap:0.5rem; align-items:center">
                            <input type="color" id="colorPicker" value="#c8a96e"
                                   oninput="document.getElementById('hexInput').value=this.value"
                                   style="width:44px; height:38px; border:1px solid var(--border2); border-radius:6px; background:var(--bg); cursor:pointer; padding:2px">
                            <input type="text" name="hex" id="hexInput" class="form-control"
                                   value="#c8a96e" placeholder="#c8a96e"
                                   oninput="syncPicker(this)"
                                   pattern="^#[0-9a-fA-F]{6}$" required
                                   style="font-family:monospace">
                        </div>
                        <div class="form-hint">Click the colour swatch to open the picker</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Label *</label>
                        <select name="label" class="form-control" required>
                            @foreach(['heartwood'=>'Heartwood','sapwood'=>'Sapwood','aged'=>'Aged','fresh_cut'=>'Fresh Cut'] as $v => $l)
                            <option value="{{ $v }}">{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="e.g. Darker when aged">
                    </div>
                    <button type="submit" class="btn btn-success">Add Color</button>
                </form>
            </div>
        </div>

    </div>
</div>

{{-- ══════════════════════ SMELL PROFILES ══════════════════════ --}}
<div id="tab-smell" style="display:none">

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; align-items:start">

        <div class="card">
            <div class="card-header"><h2>Existing Smell Profiles</h2></div>
            <div class="card-body" style="padding:0">
                @forelse($species->smellProfiles as $smell)
                <div style="display:flex; align-items:center; justify-content:space-between; padding:0.7rem 1.1rem; border-bottom:1px solid rgba(58,41,16,.4)">
                    <div>
                        <div style="font-size:0.875rem; font-weight:600">{{ ucfirst($smell->smell) }}</div>
                        <div style="font-size:0.75rem; color:var(--muted)">Intensity: {{ ucfirst($smell->intensity) }}</div>
                        @if($smell->notes)<div style="font-size:0.7rem; color:var(--muted)">{{ $smell->notes }}</div>@endif
                    </div>
                    <form method="POST" action="{{ route('admin.species.smells.destroy', [$species, $smell]) }}"
                          onsubmit="return confirm('Remove this smell profile?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger btn-sm btn-icon">✕</button>
                    </form>
                </div>
                @empty
                <div style="padding:1.1rem; color:var(--muted); font-size:0.875rem">No smell profiles yet.</div>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>＋ Add Smell Profile</h2></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.species.smells.store', $species) }}">
                    @csrf
                    <div class="form-group">
                        <label class="form-label">Smell Type *</label>
                        <select name="smell" class="form-control" required>
                            @foreach(['aromatic'=>'Aromatic / Fragrant','resinous'=>'Resinous / Piney','sweet'=>'Sweet / Vanilla-like','bitter'=>'Bitter / Astringent','musty'=>'Musty / Earthy','odorless'=>'Odorless','spicy'=>'Spicy / Pepper-like','sour'=>'Sour / Acidic'] as $v => $l)
                            <option value="{{ $v }}">{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Intensity *</label>
                        <select name="intensity" class="form-control" required>
                            <option value="faint">Faint</option>
                            <option value="moderate" selected>Moderate</option>
                            <option value="strong">Strong</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="e.g. Stronger when freshly cut">
                    </div>
                    <button type="submit" class="btn btn-success">Add Smell</button>
                </form>
            </div>
        </div>

    </div>
</div>

{{-- ══════════════════════ GRAIN PROFILE ══════════════════════ --}}
<div id="tab-grain" style="display:none">
    @php $grain = $species->grainProfile; @endphp
    <div style="max-width:680px" class="card">
        <div class="card-header">
            <h2>◈ Grain &amp; Anatomy Profile</h2>
            @if($grain)<span class="badge badge-green">Saved</span>@else<span class="badge badge-red">Not set</span>@endif
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.species.grain.store', $species) }}">
                @csrf
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Grain Pattern</label>
                        <select name="grain_pattern" class="form-control">
                            <option value="">— Not set —</option>
                            @foreach(['straight'=>'Straight','wavy'=>'Wavy','interlocked'=>'Interlocked','irregular'=>'Irregular'] as $v => $l)
                            <option value="{{ $v }}" {{ ($grain->grain_pattern ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                        <div class="form-hint">Determined by Sobel directionality analysis</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Pore Structure</label>
                        <select name="pore_type" class="form-control">
                            <option value="">— Not set —</option>
                            @foreach(['ring_porous'=>'Ring-Porous (Oak-type)','diffuse_porous'=>'Diffuse-Porous (Tropical)','closed'=>'Closed (Softwood/Fine)'] as $v => $l)
                            <option value="{{ $v }}" {{ ($grain->pore_type ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                        <div class="form-hint">Detected by Laplacian variance</div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Growth Ring Visibility</label>
                        <select name="ring_visibility" class="form-control">
                            <option value="">— Not set —</option>
                            @foreach(['strong'=>'Strong / High Contrast','faint'=>'Faint / Tight','none'=>'None'] as $v => $l)
                            <option value="{{ $v }}" {{ ($grain->ring_visibility ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Ray Pattern</label>
                        <select name="ray_visibility" class="form-control">
                            <option value="">— Not set —</option>
                            @foreach(['visible'=>'Visible (shiny lines)','not_visible'=>'Not Visible'] as $v => $l)
                            <option value="{{ $v }}" {{ ($grain->ray_visibility ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                        <div class="form-hint">Claude Vision recommended for subtle rays</div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Surface Texture</label>
                        <select name="surface_texture" class="form-control">
                            <option value="">— Not set —</option>
                            @foreach(['smooth'=>'Smooth (closed pore)','rough'=>'Rough / Grainy (open pore)'] as $v => $l)
                            <option value="{{ $v }}" {{ ($grain->surface_texture ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Special Figure</label>
                        <select name="special_figure" class="form-control">
                            @foreach(['none'=>'None','curly'=>'Curly / Flame','ribbon'=>'Ribbon','burl'=>'Burl','flame'=>'Flame'] as $v => $l)
                            <option value="{{ $v }}" {{ ($grain->special_figure ?? 'none') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                        <div class="form-hint">Premium decorative figure patterns</div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"
                              placeholder="Additional identification notes…">{{ $grain->notes ?? '' }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary">Save Grain Profile</button>
            </form>
        </div>
    </div>
</div>

{{-- ══════════════════════ REFERENCE IMAGES ══════════════════════ --}}
<div id="tab-images" style="display:none">

    {{-- Upload form --}}
    <div class="card" style="margin-bottom:1.25rem">
        <div class="card-header"><h2>⬆ Upload Reference Image</h2></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.species.images.store', $species) }}"
                  enctype="multipart/form-data">
                @csrf
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Image File *</label>
                        <input type="file" name="image" class="form-control"
                               accept="image/jpeg,image/png,image/webp" required
                               onchange="previewRef(this)">
                        <div class="form-hint">JPEG, PNG, WebP — max 8 MB</div>
                    </div>
                    <div class="form-group" style="display:flex; align-items:center">
                        <img id="refPreview" style="max-height:80px; border-radius:6px; display:none; border:1px solid var(--border)">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Cut Type *</label>
                        <select name="cut_type" class="form-control" required>
                            <option value="side_cut">Side Cut (grain surface)</option>
                            <option value="cross_section">Cross Section (annual rings)</option>
                            <option value="flat_cut">Flat Cut (end grain)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Label</label>
                        <input type="text" name="label" class="form-control"
                               placeholder="e.g. heartwood cross section">
                    </div>
                </div>
                <label style="display:flex; align-items:center; gap:0.6rem; font-size:0.875rem; cursor:pointer; margin-bottom:1rem; color:var(--text)">
                    <input type="checkbox" name="is_primary" value="1" style="accent-color:var(--gold); width:15px; height:15px">
                    Set as primary image for this cut type
                </label>
                <button type="submit" class="btn btn-primary">Upload Image</button>
            </form>
        </div>
    </div>

    {{-- Existing images grouped by cut type --}}
    @php
        $imagesByCut = $species->referenceImages->groupBy('cut_type');
        $cutLabels   = ['side_cut' => 'Side Cut', 'cross_section' => 'Cross Section', 'flat_cut' => 'Flat Cut'];
    @endphp

    @forelse($imagesByCut as $cutType => $images)
    <div class="card" style="margin-bottom:1rem">
        <div class="card-header">
            <h2>{{ $cutLabels[$cutType] ?? $cutType }} ({{ $images->count() }})</h2>
        </div>
        <div class="card-body">
            <div class="img-grid">
                @foreach($images as $img)
                <div class="img-thumb" style="{{ $img->is_primary ? 'border-color:var(--gold)' : '' }}">
                    <img src="{{ Storage::disk('public')->url($img->image_path) }}"
                         alt="{{ $img->label }}"
                         onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22><rect fill=%22%231a1208%22 width=%22100%22 height=%22100%22/><text x=%2250%22 y=%2255%22 text-anchor=%22middle%22 fill=%22%23a0855a%22 font-size=%2212%22>No img</text></svg>'">
                    @if($img->is_primary)
                        <div style="position:absolute;top:4px;left:4px;background:var(--gold);color:#000;font-size:0.6rem;padding:0.1rem 0.3rem;border-radius:3px;font-weight:700">PRIMARY</div>
                    @endif
                    @if($img->label)
                        <div class="img-thumb-label">{{ Str::limit($img->label, 20) }}</div>
                    @endif
                    <div style="position:absolute;top:4px;right:4px;display:flex;flex-direction:column;gap:3px">
                        @if(!$img->is_primary)
                        <form method="POST" action="{{ route('admin.species.images.primary', [$species, $img]) }}">
                            @csrf
                            <button class="img-thumb-del" style="color:var(--gold)" title="Set Primary">★</button>
                        </form>
                        @endif
                        <form method="POST" action="{{ route('admin.species.images.destroy', [$species, $img]) }}"
                              onsubmit="return confirm('Delete this image?')">
                            @csrf @method('DELETE')
                            <button class="img-thumb-del">✕</button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @empty
    <div class="card">
        <div class="card-body" style="color:var(--muted)">No reference images uploaded yet.</div>
    </div>
    @endforelse

</div>

@endsection

@push('scripts')
<script>
function switchTab(el, tab) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    ['colors','smell','grain','images'].forEach(id => {
        document.getElementById('tab-' + id).style.display = (id === tab) ? '' : 'none';
    });
}
function syncPicker(input) {
    if (/^#[0-9a-fA-F]{6}$/.test(input.value)) {
        document.getElementById('colorPicker').value = input.value;
    }
}
function previewRef(input) {
    const img = document.getElementById('refPreview');
    if (input.files && input.files[0]) {
        img.src = URL.createObjectURL(input.files[0]);
        img.style.display = '';
    }
}
</script>
@endpush
