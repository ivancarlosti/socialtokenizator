@extends('layouts.app')

@section('title', __('messages.settings').' — '.config('app.name'))

@section('content')
    <h1 class="text-2xl font-semibold text-white">{{ __('messages.settings_heading') }}</h1>

    @if($errors->any())
        <div class="mt-4 bg-red-900/30 border border-red-700 text-red-200 rounded px-4 py-2 text-sm">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data"
          class="mt-6 space-y-6 bg-neutral-900 border border-neutral-800 rounded p-5">
        @csrf

        {{-- Logo --}}
        <div>
            <label class="block text-sm text-neutral-300 mb-1">{{ __('messages.settings_logo') }}</label>
            @if($logoUrl)
                <div class="mb-2 flex items-center gap-3">
                    <img src="{{ $logoUrl }}" alt="" class="h-10 w-auto bg-neutral-950 border border-neutral-800 rounded">
                    <span class="text-xs text-neutral-500">{{ __('messages.current') }}</span>
                </div>
                <label class="inline-flex items-center gap-2 text-xs text-neutral-400 mb-2">
                    <input type="checkbox" name="remove_logo" value="1">
                    {{ __('messages.settings_logo_remove') }}
                </label>
            @endif
            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml"
                   class="block w-full text-sm text-neutral-300">
            <p class="mt-1 text-xs text-neutral-500">{{ __('messages.settings_logo_help') }}</p>
        </div>

        {{-- Favicon --}}
        <div>
            <label class="block text-sm text-neutral-300 mb-1">{{ __('messages.settings_favicon') }}</label>
            @if($faviconUrl)
                <div class="mb-2 flex items-center gap-3">
                    <img src="{{ $faviconUrl }}" alt="" class="h-8 w-8 bg-neutral-950 border border-neutral-800 rounded">
                    <span class="text-xs text-neutral-500">{{ __('messages.current') }}</span>
                </div>
                <label class="inline-flex items-center gap-2 text-xs text-neutral-400 mb-2">
                    <input type="checkbox" name="remove_favicon" value="1">
                    {{ __('messages.settings_favicon_remove') }}
                </label>
            @endif
            <input type="file" name="favicon" accept="image/png,image/x-icon,image/vnd.microsoft.icon,image/svg+xml,image/webp"
                   class="block w-full text-sm text-neutral-300">
            <p class="mt-1 text-xs text-neutral-500">{{ __('messages.settings_favicon_help') }}</p>
        </div>

        {{-- Default locale --}}
        <div>
            <label class="block text-sm text-neutral-300 mb-1">{{ __('messages.settings_default_locale') }}</label>
            <select name="default_locale"
                    class="bg-neutral-950 border border-neutral-700 rounded px-3 py-2 text-sm">
                @foreach($locales as $code => $info)
                    <option value="{{ $code }}" @selected($code === $defaultLocale)>{{ $info['name'] }} ({{ $code }})</option>
                @endforeach
            </select>
        </div>

        {{-- Footer links --}}
        <div>
            <label class="block text-sm text-neutral-300 mb-2">{{ __('messages.settings_footer_links') }}</label>
            <p class="text-xs text-neutral-500 mb-3">{{ __('messages.settings_footer_links_help') }}</p>
            <div class="space-y-2">
                @foreach($footerLinkRows as $i => $link)
                    <div class="flex gap-2">
                        <input type="text" name="footer_links[{{ $i }}][label]"
                               value="{{ old('footer_links.'.$i.'.label', $link['label']) }}"
                               placeholder="{{ __('messages.settings_footer_link_label') }}"
                               maxlength="60"
                               class="w-1/3 bg-neutral-950 border border-neutral-700 rounded px-3 py-2 text-sm">
                        <input type="url" name="footer_links[{{ $i }}][url]"
                               value="{{ old('footer_links.'.$i.'.url', $link['url']) }}"
                               placeholder="https://…"
                               maxlength="1024"
                               class="flex-1 bg-neutral-950 border border-neutral-700 rounded px-3 py-2 text-sm">
                    </div>
                @endforeach
            </div>
        </div>

        <div class="pt-2">
            <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white font-medium px-4 py-2 rounded">
                {{ __('messages.settings_save') }}
            </button>
        </div>
    </form>
@endsection
