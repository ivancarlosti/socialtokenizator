@extends('layouts.app')

@section('title', __('messages.search_placeholder').' — '.config('app.name'))

@section('content')
    <h1 class="text-2xl font-semibold text-white">
        {{ $q !== '' ? __('messages.search_results_for', ['q' => $q]) : __('messages.search_all_images') }}
    </h1>
    <p class="text-neutral-500 text-sm mt-1">{{ trans_choice('messages.search_match', $images->total(), ['count' => $images->total()]) }}</p>

    @if($images->isEmpty())
        <div class="text-center text-neutral-400 py-16 border border-dashed border-neutral-700 rounded mt-6">
            {{ __('messages.search_empty') }}
        </div>
    @else
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 mt-6">
            @foreach($images as $img)
                <a href="{{ route('image.show', ['uuid' => $img->uuid]) }}"
                   class="block bg-neutral-900 border border-neutral-800 rounded overflow-hidden hover:border-neutral-600">
                    <img src="{{ $img->public_url }}" alt="{{ $img->description }}"
                         class="w-full aspect-square object-cover bg-black">
                    @if($img->description)
                        <div class="px-2 py-2 text-xs text-neutral-300 truncate">
                            {{ $img->description }}
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
