@extends('layouts.app')

@section('title', '404 — Not found')

@section('content')
    <div class="text-center py-20">
        <h1 class="text-5xl font-bold text-white">404</h1>
        <p class="mt-4 text-neutral-400">Nothing here.</p>
        <a href="{{ route('home') }}" class="inline-block mt-6 text-emerald-400">← Home</a>
    </div>
@endsection
