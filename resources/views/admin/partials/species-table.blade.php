<table class="table">
    <thead>
        <tr>
            <th>Species</th>
            <th>Hardness</th>
            <th>Colors</th>
            <th>Images</th>
            <th>Grain</th>
            <th>Status</th>
            @if(empty($compact)) <th></th> @endif
        </tr>
    </thead>
    <tbody>
        @forelse($species as $sp)
        <tr>
            <td>
                <div style="font-weight:600">
                    @if($sp->is_protected) <span class="protected-dot" title="DENR Protected"></span> @endif
                    {{ $sp->name }}
                </div>
                @if($sp->scientific_name)
                <div style="font-size:0.75rem; color:var(--muted); font-style:italic">{{ $sp->scientific_name }}</div>
                @endif
            </td>
            <td>
                <span class="badge {{ $sp->hardness === 'hardwood' ? 'badge-gold' : 'badge-blue' }}">
                    {{ ucfirst($sp->hardness) }}
                </span>
            </td>
            <td>
                <span class="badge badge-muted">{{ $sp->color_references_count ?? $sp->colorReferences?->count() ?? 0 }}</span>
            </td>
            <td>
                <span class="badge badge-muted">{{ $sp->reference_images_count ?? $sp->referenceImages?->count() ?? 0 }}</span>
            </td>
            <td>
                @if($sp->grainProfile)
                    <span class="badge badge-green">✓</span>
                @else
                    <span class="badge badge-red">✗</span>
                @endif
            </td>
            <td>
                @if($sp->is_protected)
                    <span class="badge badge-red">Protected</span>
                @else
                    <span class="badge badge-muted">Unprotected</span>
                @endif
                @if($sp->cites_appendix)
                    <span class="badge badge-gold" style="margin-left:0.2rem">CITES {{ $sp->cites_appendix }}</span>
                @endif
            </td>
            @if(empty($compact))
            <td style="text-align:right">
                <a href="{{ route('admin.species.show', $sp) }}" class="btn btn-sm btn-outline">Manage</a>
            </td>
            @endif
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center; color:var(--muted); padding:1.5rem;">No species found.</td></tr>
        @endforelse
    </tbody>
</table>
