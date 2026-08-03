@extends('layouts.app')

@section('title', __('messages.categories_heading').' — '.config('app.name'))

@section('content')
    <h1 class="text-2xl font-semibold text-copy">{{ __('messages.categories_heading') }}</h1>

    @if($errors->any())
        <div class="mt-4 bg-red-900/30 border border-red-700 text-red-200 rounded px-4 py-2 text-sm">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Add new category --}}
    <form method="post" action="{{ route('admin.categories.store') }}"
          class="mt-6 flex gap-2 items-end bg-card border border-card-border rounded p-4">
        @csrf
        <div class="flex-1">
            <label class="block text-sm text-muted mb-1">{{ __('messages.category_name') }}</label>
            <input type="text" name="name" value="{{ old('name') }}" required maxlength="64"
                   class="w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy"
                   placeholder="{{ __('messages.tags_placeholder') }}">
        </div>
        <button type="submit" class="bg-accent hover:bg-accent-hover text-white font-medium px-4 py-2 rounded text-sm">
            {{ __('messages.category_add') }}
        </button>
    </form>

    {{-- Category list --}}
    @if($categories->isEmpty())
        <div class="text-center text-muted py-12 border border-dashed border-card-border rounded mt-6">
            {{ __('messages.home_no_images') }}
        </div>
    @else
        <div class="mt-6 space-y-2">
            @foreach($categories as $cat)
                <div class="flex items-center justify-between gap-4 bg-card border border-card-border rounded px-4 py-3">
                    <div class="flex items-center gap-3">
                        <span class="text-copy font-medium">{{ $cat->name }}</span>
                        <span class="text-xs text-muted">
                            {{ trans_choice('messages.category_images_count', $cat->images_count, ['count' => $cat->images_count]) }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        {{-- Rename form --}}
                        <form method="post" action="{{ route('admin.categories.update', $cat) }}" class="flex gap-1 items-center">
                            @csrf
                            @method('PUT')
                            <input type="text" name="name" value="{{ old('rename_'.$cat->id, $cat->name) }}" required maxlength="64"
                                   class="w-32 bg-input border border-input-border rounded px-2 py-1 text-xs text-copy">
                            <button type="submit" class="text-xs text-accent px-1 hover:opacity-80">
                                {{ __('messages.category_rename') }}
                            </button>
                        </form>

                        {{-- Delete --}}
                        <form method="post" action="{{ route('admin.categories.destroy', $cat) }}"
                              onsubmit="return confirm('{{ __('messages.category_confirm_delete') }}');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs text-red-400 hover:text-red-300">
                                {{ __('messages.category_delete') }}
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
