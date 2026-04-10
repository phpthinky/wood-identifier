@extends('admin.layout')
@section('title', 'Add Species')
@section('page-title', 'Add New Species')

@section('content')
<div style="max-width:720px">
    <div class="card">
        <div class="card-header">
            <h2>＋ New Wood Species</h2>
            <a href="{{ route('admin.species.index') }}" class="btn btn-outline btn-sm">← Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.species.store') }}">
                @csrf
                @include('admin.partials.species-form', ['species' => null])
                <hr class="divider">
                <button type="submit" class="btn btn-primary">Create Species →</button>
            </form>
        </div>
    </div>
</div>
@endsection
