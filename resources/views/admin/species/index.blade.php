@extends('admin.layout')
@section('title', 'Species Library')
@section('page-title', 'Species Library')

@section('content')
<div class="card">
    <div class="card-header">
        <h2>⊞ All Species ({{ $species->count() }})</h2>
        <a href="{{ route('admin.species.create') }}" class="btn btn-primary btn-sm">＋ Add Species</a>
    </div>
    <div style="padding:0">
        @include('admin.partials.species-table', ['species' => $species])
    </div>
</div>
@endsection
