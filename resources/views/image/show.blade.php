@extends('layouts.app')

@php
    $headline = $image->getHeadline($currentLocale)
        ?: ($image->getDescription($currentLocale)
            ? \Illuminate\Support\Str::limit($image->getDescription($currentLocale), 60)
            : null);

    $pageTitle = $headline
        ? $siteTitle . ' — ' . $headline
        : $siteTitle;

    // OG meta uses default locale for consistency
    $defaultLocale = \App\Models\Setting::get('default_locale', 'en');
    $ogHeadline = $image->getHeadline($defaultLocale);
    $ogDesc = $image->getDescription($defaultLocale)
        ?: __('messages.shared_via', ['app' => $siteTitle]);
    $shortDesc = \Illuminate\Support\Str::limit((string) $ogDesc, 200);
    $shareUrl = route('image.show', ['slug' => $image->short_id]);
    $imgUrl = $image->public_url;
@endphp

@section('title', $pageTitle)

@section('meta')
    <meta name="description" content="{{ $shortDesc }}">

    <meta property="og:title" content="{{ $ogHeadline ? $siteTitle . ' — ' . $ogHeadline : $siteTitle }}">
    <meta property="og:description" content="{{ $shortDesc }}">
    <meta property="og:image" content="{{ $imgUrl }}">
    @if($image->width)<meta property="og:image:width" content="{{ $image->width }}">@endif
    @if($image->height)<meta property="og:image:height" content="{{ $image->height }}">@endif
    <meta property="og:url" content="{{ $shareUrl }}">
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="{{ $siteTitle }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $ogHeadline ? $siteTitle . ' — ' . $ogHeadline : $siteTitle }}">
    <meta name="twitter:description" content="{{ $shortDesc }}">
    <meta name="twitter:image" content="{{ $imgUrl }}">

    <link rel="canonical" href="{{ $shareUrl }}">
@endsection

@section('content')
    <article class="bg-card border border-card-border rounded-lg overflow-hidden">
        <img src="{{ $imgUrl }}" alt="{{ $image->getDescription($currentLocale) }}"
             class="w-full max-h-[80vh] object-contain bg-black">

        <div class="p-5">
            @php $displayHeadline = $image->getHeadline($currentLocale); @endphp
            @if($displayHeadline)
                <h1 class="text-xl font-semibold text-copy mb-3">{{ $displayHeadline }}</h1>
            @endif

            @php $displayDesc = $image->getDescription($currentLocale); @endphp
            @if($displayDesc)
                <p class="text-copy text-base leading-relaxed">{!! nl2br(e($displayDesc)) !!}</p>
            @else
                <p class="text-muted italic">{{ __('messages.image_no_description') }}</p>
            @endif

            {{-- Tags at bottom (lowercase, no translation) --}}
            @if($image->tags->isNotEmpty())
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach($image->tags as $tag)
                        <a href="{{ route('home', ['tag' => $tag->name]) }}"
                           class="text-xs bg-card border border-card-border rounded px-2 py-0.5 text-muted hover:text-copy">
                            #{{ $tag->name }}
                        </a>
                    @endforeach
                </div>
            @endif

            @if($image->sources->isNotEmpty())
                <div class="mt-6">
                    <h2 class="text-sm uppercase tracking-wide text-muted mb-2">{{ __('messages.image_sources') }}</h2>
                    <ul class="space-y-1 text-sm">
                        @foreach($image->sources as $src)
                            <li>
                                <a href="{{ $src->url }}" target="_blank" rel="noopener noreferrer ugc"
                                   class="text-link">
                                    {{ $src->label ?: $src->url }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-6 flex flex-wrap gap-2">
                <a target="_blank" rel="noopener"
                   href="https://x.com/intent/post?url={{ urlencode($shareUrl) }}&text={{ urlencode($shortDesc) }}"
                   class="inline-flex items-center gap-2 bg-black border border-neutral-700 hover:border-white text-white px-3 py-1.5 rounded text-sm"
                   style="color: #fff">
                    {{ __('messages.share_on_x') }}
                </a>
                <a target="_blank" rel="noopener"
                   href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($shareUrl) }}"
                   class="inline-flex items-center gap-2 bg-[#1877F2] hover:brightness-110 text-white px-3 py-1.5 rounded text-sm"
                   style="color: #fff">
                    {{ __('messages.share_on_facebook') }}
                </a>
                <a target="_blank" rel="noopener"
                   href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode($shareUrl) }}"
                   class="inline-flex items-center gap-2 bg-[#0A66C2] hover:brightness-110 text-white px-3 py-1.5 rounded text-sm"
                   style="color: #fff">
                    {{ __('messages.share_on_linkedin') }}
                </a>
                <button type="button" onclick="navigator.clipboard.writeText('{{ $shareUrl }}')"
                        class="bg-neutral-800 hover:bg-neutral-700 text-neutral-200 border border-neutral-700 px-3 py-1.5 rounded text-sm">
                    {{ __('messages.copy_link') }}
                </button>
            </div>

            @if($isAdmin)
                <div class="mt-6 flex items-center gap-4">
                    <a href="{{ route('admin.images.edit', ['uuid' => $image->uuid]) }}"
                       class="text-xs text-accent">{{ __('messages.edit_image') }}</a>
                    <form method="post" action="{{ route('admin.images.destroy', ['uuid' => $image->uuid]) }}"
                          onsubmit="return confirm('{{ __('messages.confirm_delete_image') }}');"
                          class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs text-red-400 hover:text-red-300">{{ __('messages.delete_image') }}</button>
                    </form>
                </div>
            @endif
        </div>
    </article>
@endsection
