@extends('layouts.app')

@section('title', '403 — '.__('messages.forbidden'))

@section('content')
    <div class="text-center py-20">
        <h1 class="text-5xl font-bold text-white">403</h1>
        <p class="mt-4 text-neutral-400">{{ $exception->getMessage() ?: __('messages.forbidden') }}</p>
        <a href="{{ route('home') }}" class="inline-block mt-6 text-emerald-400">{{ __('messages.home_link') }}</a>
    </div>
@endsection
