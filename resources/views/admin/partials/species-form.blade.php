{{-- Reusable species basic info form fields --}}
<div class="form-row">
    <div class="form-group">
        <label class="form-label">Common Name *</label>
        <input type="text" name="name" class="form-control"
               value="{{ old('name', $species->name ?? '') }}" placeholder="e.g. Narra" required>
    </div>
    <div class="form-group">
        <label class="form-label">Local Name</label>
        <input type="text" name="local_name" class="form-control"
               value="{{ old('local_name', $species->local_name ?? '') }}" placeholder="e.g. Angsana">
    </div>
</div>

<div class="form-group">
    <label class="form-label">Scientific Name</label>
    <input type="text" name="scientific_name" class="form-control"
           value="{{ old('scientific_name', $species->scientific_name ?? '') }}"
           placeholder="e.g. Pterocarpus indicus" style="font-style:italic">
</div>

<div class="form-row-3">
    <div class="form-group">
        <label class="form-label">Hardness *</label>
        <select name="hardness" class="form-control" required>
            <option value="">— Select —</option>
            @foreach(['hardwood','softwood'] as $h)
            <option value="{{ $h }}" {{ old('hardness', $species->hardness ?? '') === $h ? 'selected' : '' }}>
                {{ ucfirst($h) }}
            </option>
            @endforeach
        </select>
    </div>
    <div class="form-group">
        <label class="form-label">Density Min (kg/m³)</label>
        <input type="number" name="density_min" class="form-control" step="1" min="0"
               value="{{ old('density_min', $species->density_min ?? '') }}" placeholder="e.g. 560">
    </div>
    <div class="form-group">
        <label class="form-label">Density Max (kg/m³)</label>
        <input type="number" name="density_max" class="form-control" step="1" min="0"
               value="{{ old('density_max', $species->density_max ?? '') }}" placeholder="e.g. 800">
    </div>
</div>

<div class="form-row">
    <div class="form-group">
        <label class="form-label">CITES Appendix</label>
        <select name="cites_appendix" class="form-control">
            <option value="">— None —</option>
            @foreach(['I','II','III'] as $c)
            <option value="{{ $c }}" {{ old('cites_appendix', $species->cites_appendix ?? '') === $c ? 'selected' : '' }}>
                Appendix {{ $c }}
            </option>
            @endforeach
        </select>
    </div>
    <div class="form-group" style="display:flex; align-items:flex-end; padding-bottom:0.1rem">
        <label style="display:flex; align-items:center; gap:0.6rem; cursor:pointer; font-size:0.875rem; color:var(--text)">
            <input type="hidden" name="is_protected" value="0">
            <input type="checkbox" name="is_protected" value="1"
                   {{ old('is_protected', $species->is_protected ?? false) ? 'checked' : '' }}
                   style="accent-color: #f87171; width:16px; height:16px;">
            <span>DENR Protected Species</span>
        </label>
    </div>
</div>

<div class="form-group">
    <label class="form-label">Description</label>
    <textarea name="description" class="form-control" rows="3"
              placeholder="Habitat, uses, distinguishing features…">{{ old('description', $species->description ?? '') }}</textarea>
</div>
