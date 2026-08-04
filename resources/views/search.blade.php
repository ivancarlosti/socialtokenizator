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
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 mt-6">
            @foreach($images as $img)
                <a href="{{ route('image.show', ['uuid' => $img->uuid]) }}"
                   class="block bg-card border border-card-border rounded overflow-hidden hover:border-card-border-hover">
                    <img src="{{ $img->public_url }}" alt="{{ $img->getDescription($currentLocale) }}"
                         class="w-full aspect-square object-cover bg-black">
                    @php $desc = $img->getDescription($currentLocale); @endphp
                    @if($desc)
                        <div class="px-2 py-2 text-xs text-copy truncate">
                            {{ $desc }}
                        </div>
                    @endif
                </a>
            @endforeach
        </div>

        <div class="mt-8">
            {{ $images->links() }}
        </div>
    @endif
@endsection
