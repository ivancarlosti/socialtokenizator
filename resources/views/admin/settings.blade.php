@extends('layouts.app')

@section('title', __('messages.settings').' — '.$siteTitle)

@section('content')
    <h1 class="text-2xl font-semibold text-copy">{{ __('messages.settings_heading') }}</h1>

    @if($errors->any())
        <div class="mt-4 bg-red-900/30 border border-red-700 text-red-200 rounded px-4 py-2 text-sm">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data"
          class="mt-6 space-y-6 bg-card border border-card-border rounded p-5">
        @csrf

        {{-- Logo --}}
        <div>
            <label class="block text-sm text-muted mb-1">{{ __('messages.settings_logo') }}</label>
            @if($logoUrl)
                <div class="mb-2 flex items-center gap-3">
                    <img src="{{ $logoUrl }}" alt="" class="h-10 w-auto bg-neutral-950 border border-neutral-800 rounded">
                    <span class="text-xs text-muted">{{ __('messages.current') }}</span>
                </div>
                <label class="inline-flex items-center gap-2 text-xs text-muted mb-2">
                    <input type="checkbox" name="remove_logo" value="1">
                    {{ __('messages.settings_logo_remove') }}
                </label>
            @endif
            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml"
                   class="block w-full text-sm text-muted">
            <p class="mt-1 text-xs text-muted">{{ __('messages.settings_logo_help') }}</p>
        </div>

        {{-- Favicon --}}
        <div>
            <label class="block text-sm text-muted mb-1">{{ __('messages.settings_favicon') }}</label>
            @if($faviconUrl)
                <div class="mb-2 flex items-center gap-3">
                    <img src="{{ $faviconUrl }}" alt="" class="h-8 w-8 bg-neutral-950 border border-neutral-800 rounded">
                    <span class="text-xs text-muted">{{ __('messages.current') }}</span>
                </div>
                <label class="inline-flex items-center gap-2 text-xs text-muted mb-2">
                    <input type="checkbox" name="remove_favicon" value="1">
                    {{ __('messages.settings_favicon_remove') }}
                </label>
            @endif
            <input type="file" name="favicon" accept="image/png,image/x-icon,image/vnd.microsoft.icon,image/svg+xml,image/webp"
                   class="block w-full text-sm text-muted">
            <p class="mt-1 text-xs text-muted">{{ __('messages.settings_favicon_help') }}</p>
        </div>

        {{-- Default theme --}}
        <div>
            <label class="block text-sm text-muted mb-1">{{ __('messages.settings_default_theme') }}</label>
            <select name="default_theme"
                    class="bg-input border border-input-border rounded px-3 py-2 text-sm text-copy">
                <option value="dark" @selected($defaultTheme === 'dark')>{{ __('messages.theme_dark') }}</option>
                <option value="light" @selected($defaultTheme === 'light')>{{ __('messages.theme_light') }}</option>
            </select>
        </div>

        {{-- Default locale --}}
        <div>
            <label class="block text-sm text-muted mb-1">{{ __('messages.settings_default_locale') }}</label>
            <select name="default_locale"
                    class="bg-input border border-input-border rounded px-3 py-2 text-sm text-copy">
                @foreach($locales as $code => $info)
                    <option value="{{ $code }}" @selected($code === $defaultLocale)>{{ $info['name'] }}</option>
                @endforeach
            </select>
        </div>

        {{-- Posts per page --}}
        <div>
            <label class="block text-sm text-muted mb-1">{{ __('messages.settings_posts_per_page') }}</label>
            <input type="number" name="posts_per_page" value="{{ old('posts_per_page', $postsPerPage) }}"
                   min="1" max="100" required
                   class="w-24 bg-input border border-input-border rounded px-3 py-2 text-sm text-copy">
            <p class="mt-1 text-xs text-muted">{{ __('messages.settings_posts_per_page_help') }}</p>
        </div>

        {{-- Feed posts count --}}
        <div>
            <label class="block text-sm text-muted mb-1">{{ __('messages.settings_feed_posts_count') }}</label>
            <input type="number" name="feed_posts_count" value="{{ old('feed_posts_count', $feedPostsCount) }}"
                   min="1" max="100" required
                   class="w-24 bg-input border border-input-border rounded px-3 py-2 text-sm text-copy">
            <p class="mt-1 text-xs text-muted">{{ __('messages.settings_feed_posts_count_help') }}</p>
        </div>

        {{-- Post path prefix --}}
        <div>
            <label class="block text-sm text-muted mb-1">{{ __('messages.settings_post_path_prefix') }}</label>
            <input type="text" name="post_path_prefix" value="{{ old('post_path_prefix', $postPathPrefix) }}"
                   placeholder="p"
                   maxlength="16"
                   class="w-24 bg-input border border-input-border rounded px-3 py-2 text-sm text-copy">
            <p class="mt-1 text-xs text-muted">{{ __('messages.settings_post_path_prefix_help') }}</p>
        </div>

        {{-- Hide title section --}}
        <div>
            <label class="inline-flex items-center gap-2 text-sm text-copy">
                <input type="hidden" name="hide_title_section" value="0">
                <input type="checkbox" name="hide_title_section" value="1"
                       @checked($hideTitleSection)>
                {{ __('messages.settings_hide_title_section') }}
            </label>
            <p class="mt-1 text-xs text-muted">{{ __('messages.settings_hide_title_section_help') }}</p>
        </div>

        {{-- Hide filter label --}}
        <div>
            <label class="inline-flex items-center gap-2 text-sm text-copy">
                <input type="hidden" name="hide_filter_label" value="0">
                <input type="checkbox" name="hide_filter_label" value="1"
                       @checked($hideFilterLabel)>
                {{ __('messages.settings_hide_filter_label') }}
            </label>
            <p class="mt-1 text-xs text-muted">{{ __('messages.settings_hide_filter_label_help') }}</p>
        </div>

        {{-- Site title & subtitle per locale --}}
        <div>
            <label class="block text-sm text-muted mb-3">{{ __('messages.settings_site_title_subtitle') }}</label>
            <div class="space-y-4">
                @foreach($locales as $code => $info)
                    <div class="border border-card-border rounded p-3">
                        <p class="text-xs font-semibold text-muted mb-2">{{ $info['name'] }}</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-muted mb-1">{{ __('messages.settings_site_title') }}</label>
                                <input type="text" name="site_title_{{ $code }}"
                                       value="{{ old("site_title_{$code}", $titleRows[$code] ?? '') }}"
                                       placeholder="{{ config('app.name') }}"
                                       maxlength="120"
                                       class="w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy">
                            </div>
                            <div>
                                <label class="block text-xs text-muted mb-1">{{ __('messages.settings_site_subtitle') }}</label>
                                <input type="text" name="site_subtitle_{{ $code }}"
                                       value="{{ old("site_subtitle_{$code}", $subtitleRows[$code] ?? '') }}"
                                       placeholder="{{ __('messages.settings_site_subtitle_placeholder') }}"
                                       maxlength="200"
                                       class="w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy">
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Footer HTML per locale --}}
        <div>
            <label class="block text-sm text-muted mb-3">{{ __('messages.settings_footer_html') }}</label>
            <p class="text-xs text-muted mb-2">{{ __('messages.settings_footer_html_help') }}</p>
            <div class="space-y-4">
                @foreach($locales as $code => $info)
                    <div class="border border-card-border rounded p-3">
                        <p class="text-xs font-semibold text-muted mb-2">{{ $info['name'] }}</p>
                        <textarea name="footer_html_{{ $code }}" rows="4" maxlength="10000"
                                  class="w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy font-mono"
                                  placeholder="<p>Your custom footer text or HTML</p>">{{ old("footer_html_{$code}", $footerHtmlRows[$code] ?? '') }}</textarea>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Footer links --}}
        <div>
            <label class="block text-sm text-muted mb-2">{{ __('messages.settings_footer_links') }}</label>
            <p class="text-xs text-muted mb-3">{{ __('messages.settings_footer_links_help') }}</p>
            <div class="space-y-2">
                @foreach($footerLinkRows as $i => $link)
                    <div class="flex gap-2">
                        <input type="text" name="footer_links[{{ $i }}][label]"
                               value="{{ old('footer_links.'.$i.'.label', $link['label']) }}"
                               placeholder="{{ __('messages.settings_footer_link_label') }}"
                               maxlength="60"
                               class="w-1/3 bg-input border border-input-border rounded px-3 py-2 text-sm text-copy">
                        <input type="url" name="footer_links[{{ $i }}][url]"
                               value="{{ old('footer_links.'.$i.'.url', $link['url']) }}"
                               placeholder="https://…"
                               maxlength="1024"
                               class="flex-1 bg-input border border-input-border rounded px-3 py-2 text-sm text-copy">
                    </div>
                @endforeach
            </div>
        </div>

        <div class="pt-2">
            <button type="submit" class="bg-accent hover:bg-accent-hover text-white font-medium px-4 py-2 rounded">
                {{ __('messages.settings_save') }}
            </button>
        </div>
    </form>
@endsection
