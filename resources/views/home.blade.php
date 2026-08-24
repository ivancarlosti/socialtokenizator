@extends('layouts.app')

@php
    $homeTitle = $siteTitle;
    if ($siteSubtitle) {
        $homeTitle .= ' — ' . $siteSubtitle;
    }
@endphp

@section('title', $homeTitle)

@section('content')
    @if(!($hideTitleSection ?? false))
    <section class="text-center mb-8">
        <h1 class="text-3xl font-semibold text-copy">{{ $siteTitle }}</h1>
        @if($siteSubtitle)
            <p class="text-muted mt-1 text-sm">{{ $siteSubtitle }}</p>
        @endif
    </section>
    @endif

    {{-- Category filter chips (top of page) --}}
    @if($categories->isNotEmpty())
        <div class="mb-8">
            @if(!($hideFilterLabel ?? false))
            <p class="text-xs uppercase tracking-wide text-muted mb-2">{{ __('messages.filter_by_category') }}</p>
            @endif
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('home') }}"
                   class="inline-block px-3 py-1.5 rounded-full text-sm border transition-colors
                          {{ $categoryHandle === '' ? 'bg-accent border-accent text-white' : 'bg-card border-card-border text-muted hover:text-copy hover:border-card-border-hover' }}">
                    {{ __('messages.all_categories') }}
                </a>
                @foreach($categories as $cat)
                    <a href="{{ route('home', ['category' => $cat->handle]) }}"
                       class="inline-block px-3 py-1.5 rounded-full text-sm border transition-colors
                              {{ $categoryHandle === $cat->handle ? 'bg-accent border-accent text-white' : 'bg-card border-card-border text-muted hover:text-copy hover:border-card-border-hover' }}">
                        {{ $cat->getName($currentLocale) }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Feed --}}
    @if($images->isEmpty())
        <div class="text-center text-muted py-16 border border-dashed border-card-border rounded">
            @if($categoryHandle !== '')
                {{ __('messages.no_posts') }}
            @elseif($tagFilter !== '')
                {{ __('messages.no_posts') }}
            @else
                {{ __('messages.home_no_images') }}
                @if($isAdmin)
                    <a href="{{ route('admin.upload.create') }}" class="text-accent">{{ __('messages.home_upload_first') }}</a>
                @endif
            @endif
        </div>
    @else
        <div class="space-y-8">
            @foreach($images as $img)
                <article class="bg-card border border-card-border rounded-lg overflow-hidden">
                    <a href="{{ route('image.show', ['slug' => $img->short_id]) }}" class="block">
                        <img src="{{ $img->public_url }}" alt="{{ $img->getDescription($currentLocale) }}"
                             class="w-full max-h-[80vh] object-contain bg-black">
                    </a>
                    <div class="p-5">
                        @php $headline = $img->getHeadline($currentLocale); @endphp
                        @if($headline)
                            <h2 class="text-lg font-semibold text-copy">
                                <a href="{{ route('image.show', ['slug' => $img->short_id]) }}" class="hover:underline">{{ $headline }}</a>
                            </h2>
                        @endif

                        {{-- Tags at bottom of post (lowercase, no translation) --}}
                        @if($img->tags->isNotEmpty())
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach($img->tags as $tag)
                                    <a href="{{ route('home', ['tag' => $tag->name]) }}"
                                       class="text-xs bg-card border border-card-border rounded px-2 py-0.5 text-muted hover:text-copy">
                                        #{{ $tag->name }}
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        <div class="mt-4 text-xs text-muted">
                            <a href="{{ route('image.show', ['slug' => $img->short_id]) }}" class="text-link">
                                {{ __('messages.view_detail') }}
                            </a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-10">
            {{ $images->links() }}
        </div>
    @endif
@endsection
