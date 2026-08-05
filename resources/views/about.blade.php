@extends('layouts.app')

@section('title', __('messages.about_page_heading').' — '.$siteTitle)

@section('content')
    <h1 class="text-2xl font-semibold text-copy mb-6">{{ __('messages.about_page_heading') }}</h1>

    @if($aboutContent)
        <div class="prose prose-invert max-w-none text-copy leading-relaxed space-y-4 about-content">
            {!! $aboutContent !!}
        </div>
    @else
        <p class="text-muted">{{ __('messages.not_found') }}</p>
    @endif
@endsection
