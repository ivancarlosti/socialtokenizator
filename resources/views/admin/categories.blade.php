@extends('layouts.app')

@section('title', __('messages.categories_heading').' — '.$siteTitle)

@section('content')
    <h1 class="text-2xl font-semibold text-copy">{{ __('messages.categories_heading') }}</h1>
    <p class="text-sm text-muted mt-1">{{ __('messages.categories_help') }}</p>

    @if($errors->any())
        <div class="mt-4 bg-red-900/30 border border-red-700 text-red-200 rounded px-4 py-2 text-sm">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Add new category --}}
    <form method="post" action="{{ route('admin.categories.store') }}"
          class="mt-6 bg-card border border-card-border rounded p-4 space-y-3">
        @csrf
        <p class="text-sm font-semibold text-copy">{{ __('messages.category_add_new') }}</p>

        <div>
            <label class="block text-xs text-muted mb-1">{{ __('messages.category_handle') }}</label>
            <input type="text" name="handle" value="{{ old('handle') }}" required maxlength="64"
                   class="w-64 bg-input border border-input-border rounded px-3 py-2 text-sm text-copy"
                   placeholder="gaming-market">
            <p class="text-xs text-muted mt-0.5">{{ __('messages.category_handle_help') }}</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            @foreach(\App\Support\Locales::supported() as $code => $info)
                @php $col = 'name_'.str_replace('-', '_', $code); @endphp
                <div>
                    <label class="block text-xs text-muted mb-1">{{ $info['name'] }}</label>
                    <input type="text" name="{{ $col }}" value="{{ old($col) }}" maxlength="128"
                           class="w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy"
                           placeholder="{{ __('messages.category_name_placeholder', ['lang' => $info['name']]) }}">
                </div>
            @endforeach
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
        <div class="mt-6 space-y-3">
            @foreach($categories as $cat)
                <div class="bg-card border border-card-border rounded px-4 py-3">
                    <form method="post" action="{{ route('admin.categories.update', $cat) }}" class="space-y-3">
                        @csrf
                        @method('PUT')

                        <div class="flex items-center gap-3 flex-wrap">
                            <span class="text-xs bg-neutral-800 text-neutral-300 rounded px-2 py-0.5 font-mono">{{ $cat->handle }}</span>
                            <span class="text-xs text-muted">
                                {{ trans_choice('messages.category_images_count', $cat->images_count, ['count' => $cat->images_count]) }}
                            </span>
                        </div>

                        <div>
                            <label class="block text-xs text-muted mb-1">{{ __('messages.category_handle') }}</label>
                            <input type="text" name="handle" value="{{ old('handle_'.$cat->id, $cat->handle) }}" required maxlength="64"
                                   class="w-64 bg-input border border-input-border rounded px-3 py-2 text-sm text-copy">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            @foreach(\App\Support\Locales::supported() as $code => $info)
                                @php $col = 'name_'.str_replace('-', '_', $code); @endphp
                                <div>
                                    <label class="block text-xs text-muted mb-1">{{ $info['name'] }}</label>
                                    <input type="text" name="{{ $col }}" value="{{ old($col.'_'.$cat->id, $cat->$col) }}" maxlength="128"
                                           class="w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy"
                                           placeholder="{{ $info['name'] }}">
                                </div>
                            @endforeach
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="submit" class="text-xs text-accent px-1 hover:opacity-80">
                                {{ __('messages.category_save') }}
                            </button>
                            <button type="button"
                                    onclick="if(confirm('{{ __('messages.category_confirm_delete') }}')){this.closest('.bg-card').querySelector('.delete-form').submit()}"
                                    class="text-xs text-red-400 hover:text-red-300">
                                {{ __('messages.category_delete') }}
                            </button>
                        </div>
                    </form>
                    <form method="post" action="{{ route('admin.categories.destroy', $cat) }}" class="delete-form hidden">
                        @csrf
                        @method('DELETE')
                    </form>
                </div>
            @endforeach
        </div>
    @endif
@endsection
