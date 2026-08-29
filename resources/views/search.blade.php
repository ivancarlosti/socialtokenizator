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

                        @if(($showPostAuthorInList ?? false) || ($showPostPublishedInList ?? false) || ($showPostUpdatedInList ?? false))
                            <div class="mt-2 text-xs text-muted space-y-1">
                                @if(($showPostAuthorInList ?? false) && $img->author)
                                    <p>{{ __('messages.post_author') }}:
                                        @if($img->author->url)
                                            <a href="{{ $img->author->url }}" target="_blank" rel="noopener noreferrer" class="text-link">{{ $img->author->displayName() }}</a>
                                        @else
                                            {{ $img->author->displayName() }}
                                        @endif
                                    </p>
                                @endif
                                @if($showPostPublishedInList ?? false)
                                    <p>{{ __('messages.post_published') }}: {{ $img->created_at->setTimezone($siteTimezone)->format('Y-m-d H:i') }}</p>
                                @endif
                                @if(($showPostUpdatedInList ?? false) && $img->updated_at && $img->updated_at->greaterThan($img->created_at))
                                    <p>{{ __('messages.post_updated') }}: {{ $img->updated_at->setTimezone($siteTimezone)->format('Y-m-d H:i') }}</p>
                                @endif
                            </div>
                        @endif

                        @if($showPostDescriptionInList ?? false)
                            @php
                                $listDesc = $img->getDescription($currentLocale);
                                if ($listDesc) {
                                    $listDesc = ($postDescriptionInListMode ?? 'excerpt') === 'full'
                                        ? $listDesc
                                        : \Illuminate\Support\Str::limit($listDesc, (int) ($postDescriptionInListLength ?? 300));
                                }
                            @endphp
                            @if($listDesc)
                                <div class="mt-3 text-sm text-muted leading-relaxed">
                                    {!! nl2br(e($listDesc)) !!}
                                </div>
                            @endif
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
