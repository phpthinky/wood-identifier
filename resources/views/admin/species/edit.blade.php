@extends('admin.layout')
@section('title', 'Edit ' . $species->name)
@section('page-title', 'Edit Species')

@section('content')
<div style="max-width:720px">
    <div class="card">
        <div class="card-header">
            <h2>✎ Edit — {{ $species->name }}</h2>
            <a href="{{ route('admin.species.show', $species) }}" class="btn btn-outline btn-sm">← Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.species.update', $species) }}">
                @csrf @method('PUT')
                @include('admin.partials.species-form', ['species' => $species])
                <hr class="divider">
                <div style="display:flex; justify-content:space-between; align-items:center">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <form method="POST" action="{{ route('admin.species.destroy', $species) }}"
                          onsubmit="return confirm('Delete {{ $species->name }} and ALL related data?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Delete Species</button>
                    </form>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
