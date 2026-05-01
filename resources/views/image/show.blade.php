@extends('layouts.app')

@php
    $title = $image->description
        ? \Illuminate\Support\Str::limit($image->description, 60)
        : config('app.name');
    $shortDesc = $image->description
        ? \Illuminate\Support\Str::limit($image->description, 200)
        : __('messages.shared_via', ['app' => config('app.name')]);
    $shareUrl = url()->current();
    $imgUrl = $image->public_url;
@endphp

@section('title', $title)

@section('meta')
    <meta name="description" content="{{ $shortDesc }}">

    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $shortDesc }}">
    <meta property="og:image" content="{{ $imgUrl }}">
    @if($image->width)<meta property="og:image:width" content="{{ $image->width }}">@endif
    @if($image->height)<meta property="og:image:height" content="{{ $image->height }}">@endif
    <meta property="og:url" content="{{ $shareUrl }}">
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="{{ config('app.name') }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $shortDesc }}">
    <meta name="twitter:image" content="{{ $imgUrl }}">

    <link rel="canonical" href="{{ $shareUrl }}">
@endsection

@section('content')
    <article class="bg-neutral-900 border border-neutral-800 rounded-lg overflow-hidden">
        <img src="{{ $imgUrl }}" alt="{{ $image->description }}"
             class="w-full max-h-[80vh] object-contain bg-black">

        <div class="p-5">
            @if($image->description)
                <p class="text-neutral-100 text-base leading-relaxed whitespace-pre-line">{{ $image->description }}</p>
            @else
                <p class="text-neutral-500 italic">{{ __('messages.image_no_description') }}</p>
            @endif

            @if($image->tags->isNotEmpty())
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach($image->tags as $tag)
                        <a href="{{ route('search', ['q' => $tag->name]) }}"
                           class="text-xs bg-neutral-800 border border-neutral-700 rounded px-2 py-0.5 text-neutral-300 hover:text-white">
                            #{{ $tag->name }}
                        </a>
                    @endforeach
                </div>
            @endif

            @if($image->sources->isNotEmpty())
                <div class="mt-6">
                    <h2 class="text-sm uppercase tracking-wide text-neutral-400 mb-2">{{ __('messages.image_sources') }}</h2>
                    <ul class="space-y-1 text-sm">
                        @foreach($image->sources as $src)
                            <li>
                                <a href="{{ $src->url }}" target="_blank" rel="noopener noreferrer ugc">
                                    {{ $src->label ?: $src->url }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-6 flex flex-wrap gap-2">
                <a target="_blank" rel="noopener"
                   href="https://twitter.com/intent/tweet?url={{ urlencode($shareUrl) }}&text={{ urlencode($shortDesc) }}"
                   class="inline-flex items-center gap-2 bg-black border border-neutral-700 hover:border-white text-white px-3 py-1.5 rounded text-sm">
                    {{ __('messages.share_on_x') }}
                </a>
                <a target="_blank" rel="noopener"
                   href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($shareUrl) }}"
                   class="inline-flex items-center gap-2 bg-[#1877F2] hover:brightness-110 text-white px-3 py-1.5 rounded text-sm">
                    {{ __('messages.share_on_facebook') }}
                </a>
                <button type="button" onclick="navigator.clipboard.writeText('{{ $shareUrl }}')"
                        class="bg-neutral-800 hover:bg-neutral-700 text-neutral-200 border border-neutral-700 px-3 py-1.5 rounded text-sm">
                    {{ __('messages.copy_link') }}
                </button>
            </div>

            @if($isAdmin)
                <form method="post" action="{{ route('admin.images.destroy', ['uuid' => $image->uuid]) }}"
                      onsubmit="return confirm('{{ __('messages.confirm_delete_image') }}');"
                      class="mt-6">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-xs text-red-400 hover:text-red-300">{{ __('messages.delete_image') }}</button>
                </form>
            @endif
        </div>
    </article>
@endsection
