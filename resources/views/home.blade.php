@extends('layouts.app')

@section('content')
    <section class="text-center mb-10">
        <h1 class="text-3xl font-semibold text-white">{{ __('messages.home_heading') }}</h1>
        <p class="text-neutral-400 mt-1 text-sm">{{ __('messages.home_subheading') }}</p>
    </section>

    @if($image)
        <article class="bg-neutral-900 border border-neutral-800 rounded-lg overflow-hidden">
            <a href="{{ route('image.show', ['uuid' => $image->uuid]) }}" class="block">
                <img src="{{ $image->public_url }}" alt="{{ $image->description }}"
                     class="w-full max-h-[70vh] object-contain bg-black">
            </a>
            <div class="p-4">
                @if($image->description)
                    <p class="text-neutral-200">{{ \Illuminate\Support\Str::limit($image->description, 240) }}</p>
                @endif
                @if($image->tags->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach($image->tags as $tag)
                            <a href="{{ route('search', ['q' => $tag->name]) }}"
                               class="text-xs bg-neutral-800 border border-neutral-700 rounded px-2 py-0.5 text-neutral-300 hover:text-white">
                                #{{ $tag->name }}
                            </a>
                        @endforeach
                    </div>
                @endif
                <div class="mt-3 text-xs text-neutral-500">
                    <a href="{{ route('image.show', ['uuid' => $image->uuid]) }}">{{ __('messages.view_detail') }}</a>
                </div>
            </div>
        </article>
    @else
        <div class="text-center text-neutral-400 py-16 border border-dashed border-neutral-700 rounded">
            {{ __('messages.home_no_images') }}
            @if($isAdmin)
                <a href="{{ route('admin.upload.create') }}" class="text-emerald-400">{{ __('messages.home_upload_first') }}</a>
            @endif
        </div>
    @endif
@endsection
