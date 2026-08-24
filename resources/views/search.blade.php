@extends('layouts.app')

@section('title', __('messages.search_placeholder').' — '.$siteTitle)

@section('content')
    <h1 class="text-2xl font-semibold text-copy">
        {{ $q !== '' ? __('messages.search_results_for', ['q' => $q]) : __('messages.search_all_images') }}
    </h1>
    <p class="text-muted text-sm mt-1">{{ trans_choice('messages.search_match', $images->total(), ['count' => $images->total()]) }}</p>

    @if($images->isEmpty())
        <div class="text-center text-muted py-16 border border-dashed border-card-border rounded mt-6">
            {{ __('messages.search_empty') }}
        </div>
    @else
        <div class="space-y-8 mt-6">
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

        <div class="mt-8">
            {{ $images->links() }}
        </div>
    @endif
@endsection
