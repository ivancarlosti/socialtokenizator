@extends('layouts.app')

@section('title', __('messages.settings').' — '.$siteTitle)

@section('content')
    <h1 class="text-2xl font-semibold text-copy mb-6">{{ __('messages.settings_heading') }}</h1>

    @if($errors->any())
        <div class="mt-4 bg-red-900/30 border border-red-700 text-red-200 rounded px-4 py-2 text-sm">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="mt-6" id="settings-form">
        @csrf

        {{-- Tab navigation --}}
        <div class="flex flex-wrap gap-2 mb-6 border-b border-card-border pb-3" role="tablist">
            <button type="button" class="tab-btn px-4 py-2 rounded-t text-sm font-medium transition-colors"
                    data-tab="appearance" role="tab" aria-selected="true">
                {{ __('messages.settings_tab_appearance') }}
            </button>
            <button type="button" class="tab-btn px-4 py-2 rounded-t text-sm font-medium transition-colors"
                    data-tab="footer" role="tab" aria-selected="false">
                {{ __('messages.settings_tab_footer') }}
            </button>
            <button type="button" class="tab-btn px-4 py-2 rounded-t text-sm font-medium transition-colors"
                    data-tab="about" role="tab" aria-selected="false">
                {{ __('messages.settings_tab_about') }}
            </button>
            <button type="button" class="tab-btn px-4 py-2 rounded-t text-sm font-medium transition-colors"
                    data-tab="ai" role="tab" aria-selected="false">
                {{ __('messages.settings_tab_ai') }}
            </button>
            <button type="button" class="tab-btn px-4 py-2 rounded-t text-sm font-medium transition-colors"
                    data-tab="restapi" role="tab" aria-selected="false">
                {{ __('messages.settings_tab_restapi') }}
            </button>
            <button type="button" class="tab-btn px-4 py-2 rounded-t text-sm font-medium transition-colors"
                    data-tab="webstandards" role="tab" aria-selected="false">
                {{ __('messages.settings_tab_web_standards') }}
            </button>
            <button type="button" class="tab-btn px-4 py-2 rounded-t text-sm font-medium transition-colors"
                    data-tab="users" role="tab" aria-selected="false">
                {{ __('messages.settings_tab_users') }}
            </button>
        </div>

        {{-- ============================================ --}}
        {{-- TAB: Appearance --}}
        {{-- ============================================ --}}
        <div class="tab-panel bg-card border border-card-border rounded p-5 space-y-6" data-tab="appearance" role="tabpanel">
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
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml,image/avif"
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

            {{-- Short post ID --}}
            <div>
                <label class="block text-sm text-muted mb-1">{{ __('messages.settings_short_id_length') }}</label>
                <input type="number" name="short_id_length" value="{{ old('short_id_length', $shortIdLength) }}"
                       min="3" max="32" required
                       class="w-24 bg-input border border-input-border rounded px-3 py-2 text-sm text-copy">
                <p class="mt-1 text-xs text-muted">{{ __('messages.settings_short_id_length_help') }}</p>

                <label class="inline-flex items-center gap-2 text-sm text-copy mt-3">
                    <input type="hidden" name="short_id_uppercase" value="0">
                    <input type="checkbox" name="short_id_uppercase" value="1"
                           @checked($shortIdUppercase)>
                    {{ __('messages.settings_short_id_uppercase') }}
                </label>

                <label class="inline-flex items-center gap-2 text-sm text-copy mt-2 block">
                    <input type="hidden" name="short_id_numbers" value="0">
                    <input type="checkbox" name="short_id_numbers" value="1"
                           @checked($shortIdNumbers)>
                    {{ __('messages.settings_short_id_numbers') }}
                </label>

                <p class="mt-1 text-xs text-muted">{{ __('messages.settings_short_id_help') }}</p>
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

            {{-- Show post author & date/time --}}
            <div>
                <label class="inline-flex items-center gap-2 text-sm text-copy">
                    <input type="hidden" name="show_post_author" value="0">
                    <input type="checkbox" name="show_post_author" value="1"
                           @checked($showPostAuthor)>
                    {{ __('messages.settings_show_post_author') }}
                </label>
                <p class="mt-1 text-xs text-muted">{{ __('messages.settings_show_post_author_help') }}</p>

                <label class="inline-flex items-center gap-2 text-sm text-copy mt-3 block">
                    <input type="hidden" name="show_post_published" value="0">
                    <input type="checkbox" name="show_post_published" value="1"
                           @checked($showPostPublished)>
                    {{ __('messages.settings_show_post_published') }}
                </label>
                <p class="mt-1 text-xs text-muted">{{ __('messages.settings_show_post_published_help') }}</p>

                <label class="inline-flex items-center gap-2 text-sm text-copy mt-3 block">
                    <input type="hidden" name="show_post_updated" value="0">
                    <input type="checkbox" name="show_post_updated" value="1"
                           @checked($showPostUpdated)>
                    {{ __('messages.settings_show_post_updated') }}
                </label>
                <p class="mt-1 text-xs text-muted">{{ __('messages.settings_show_post_updated_help') }}</p>
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
        </div>

        {{-- ============================================ --}}
        {{-- TAB: Footer --}}
        {{-- ============================================ --}}
        <div class="tab-panel hidden bg-card border border-card-border rounded p-5 space-y-6" data-tab="footer" role="tabpanel">
            {{-- Footer left text per locale --}}
            <div>
                <label class="block text-sm text-muted mb-3">{{ __('messages.settings_footer_text') }}</label>
                <p class="text-xs text-muted mb-2">{{ __('messages.settings_footer_text_help') }}</p>
                <div class="space-y-4">
                    @foreach($locales as $code => $info)
                        @php
                            $allLocales = \App\Support\Locales::supported();
                            $otherLocales = array_filter($allLocales, fn($l, $k) => $k !== $code, ARRAY_FILTER_USE_BOTH);
                        @endphp
                        <div class="border border-card-border rounded p-3">
                            <p class="text-xs font-semibold text-muted mb-2">{{ $info['name'] }}</p>
                            <textarea name="footer_text_{{ $code }}" rows="4" maxlength="10000"
                                      class="w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy font-mono footer-text-field"
                                      data-locale="{{ $code }}"
                                      placeholder="<p>Your custom footer text or HTML</p>">{{ old("footer_text_{$code}", $footerTextRows[$code] ?? '') }}</textarea>
                            <div class="flex flex-wrap items-center gap-1 mt-1">
                                @foreach($otherLocales as $srcCode => $srcInfo)
                                    <button type="button" class="ai-translate-link text-xs text-accent inline-block"
                                            data-target="footer_text_{{ $code }}"
                                            data-target-locale="{{ $code }}"
                                            data-source-locale="{{ $srcCode }}"
                                            data-field-type="footer_text">
                                        {{ __('messages.translate_with_ai_from', ['locale' => $srcInfo['name']]) }}
                                    </button>
                                @endforeach
                                <span class="ai-translate-status text-xs text-muted ml-2 hidden"></span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Footer right text per locale --}}
            <div>
                <label class="block text-sm text-muted mb-3">{{ __('messages.settings_footer_html') }}</label>
                <p class="text-xs text-muted mb-2">{{ __('messages.settings_footer_html_help') }}</p>
                <div class="space-y-4">
                    @foreach($locales as $code => $info)
                        @php
                            $allLocales = \App\Support\Locales::supported();
                            $otherLocales = array_filter($allLocales, fn($l, $k) => $k !== $code, ARRAY_FILTER_USE_BOTH);
                        @endphp
                        <div class="border border-card-border rounded p-3">
                            <p class="text-xs font-semibold text-muted mb-2">{{ $info['name'] }}</p>
                            <textarea name="footer_html_{{ $code }}" rows="4" maxlength="10000"
                                      class="w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy font-mono footer-html-field"
                                      data-locale="{{ $code }}"
                                      placeholder="<p>Your custom footer text or HTML</p>">{{ old("footer_html_{$code}", $footerHtmlRows[$code] ?? '') }}</textarea>
                            <div class="flex flex-wrap items-center gap-1 mt-1">
                                @foreach($otherLocales as $srcCode => $srcInfo)
                                    <button type="button" class="ai-translate-link text-xs text-accent inline-block"
                                            data-target="footer_html_{{ $code }}"
                                            data-target-locale="{{ $code }}"
                                            data-source-locale="{{ $srcCode }}"
                                            data-field-type="footer_html">
                                        {{ __('messages.translate_with_ai_from', ['locale' => $srcInfo['name']]) }}
                                    </button>
                                @endforeach
                                <span class="ai-translate-status text-xs text-muted ml-2 hidden"></span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ============================================ --}}
        {{-- TAB: About --}}
        {{-- ============================================ --}}
        <div class="tab-panel hidden bg-card border border-card-border rounded p-5 space-y-6" data-tab="about" role="tabpanel">
            <div>
                <label class="block text-sm text-muted mb-3">{{ __('messages.about_page_heading') }}</label>
                <p class="text-xs text-muted mb-2">{{ __('messages.about_page_help') }}</p>
                <div class="space-y-4">
                    @foreach($locales as $code => $info)
                        @php
                            $allLocales = \App\Support\Locales::supported();
                            $otherLocales = array_filter($allLocales, fn($l, $k) => $k !== $code, ARRAY_FILTER_USE_BOTH);
                        @endphp
                        <div class="border border-card-border rounded p-3">
                            <p class="text-xs font-semibold text-muted mb-2">{{ $info['name'] }}</p>
                            <textarea name="about_page_{{ $code }}" rows="10" maxlength="20000"
                                      class="w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy font-mono about-field"
                                      data-locale="{{ $code }}"
                                      placeholder="<h2>{{ __('messages.about_page_heading') }}</h2><p>...</p>">{{ old("about_page_{$code}", $aboutRows[$code] ?? '') }}</textarea>
                            <div class="flex flex-wrap items-center gap-1 mt-1">
                                @foreach($otherLocales as $srcCode => $srcInfo)
                                    <button type="button" class="ai-translate-link text-xs text-accent inline-block"
                                            data-target="about_page_{{ $code }}"
                                            data-target-locale="{{ $code }}"
                                            data-source-locale="{{ $srcCode }}"
                                            data-field-type="about">
                                        {{ __('messages.translate_with_ai_from', ['locale' => $srcInfo['name']]) }}
                                    </button>
                                @endforeach
                                <span class="ai-translate-status text-xs text-muted ml-2 hidden"></span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ============================================ --}}
        {{-- TAB: Artificial Intelligence --}}
        {{-- ============================================ --}}
        <div class="tab-panel hidden bg-card border border-card-border rounded p-5 space-y-6" data-tab="ai" role="tabpanel">
            <div>
                <label class="block text-sm text-muted mb-1">{{ __('messages.settings_ai_generate_prompt') }}</label>
                <p class="text-xs text-muted mb-2">{{ __('messages.settings_ai_generate_prompt_help') }}</p>
                <textarea name="ai_generate_prompt" rows="20" maxlength="20000"
                          class="w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy font-mono"
                          placeholder="Leave empty to use the default prompt.">{{ old('ai_generate_prompt', $aiGeneratePrompt) }}</textarea>
            </div>
        </div>

        {{-- ============================================ --}}
        {{-- TAB: RestAPI --}}
        {{-- ============================================ --}}
        <div class="tab-panel hidden bg-card border border-card-border rounded p-5 space-y-6" data-tab="restapi" role="tabpanel">
            <div>
                <label class="block text-sm font-semibold text-copy mb-2">{{ __('messages.settings_api_token') }}</label>
                <p class="text-xs text-muted mb-3">{{ __('messages.settings_api_token_help') }}</p>

                @if($apiToken)
                    <div class="flex items-center gap-3 mb-3">
                        <code class="bg-input border border-input-border rounded px-3 py-2 text-sm text-copy font-mono select-all"
                              id="api-token-display">{{ substr($apiToken, 0, 12) }}&hellip;</code>
                        <button type="button" id="copy-api-token"
                                class="text-xs text-accent hover:underline whitespace-nowrap"
                                data-token="{{ $apiToken }}">
                            {{ __('messages.settings_api_token_copy') }}
                        </button>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="submit" name="api_token_action" value="regenerate"
                                class="bg-red-700 hover:bg-red-600 text-white text-sm px-3 py-1.5 rounded"
                                onclick="return confirm(@json(__('messages.settings_api_token_regenerate_confirm')))">
                            {{ __('messages.settings_api_token_regenerate') }}
                        </button>
                    </div>
                @else
                    <button type="submit" name="api_token_action" value="generate"
                            class="bg-accent hover:bg-accent-hover text-white text-sm px-4 py-2 rounded">
                        {{ __('messages.settings_api_token_generate') }}
                    </button>
                @endif
            </div>

            <div>
                <label class="block text-sm font-semibold text-copy mb-2">{{ __('messages.settings_api_allowed_ips') }}</label>
                <p class="text-xs text-muted mb-2">{{ __('messages.settings_api_allowed_ips_help') }}</p>
                <textarea name="api_allowed_ips" rows="6"
                          class="w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy font-mono"
                          placeholder="203.0.113.10&#10;203.0.113.0/24&#10;2001:db8::1&#10;2001:db8::/48">{{ old('api_allowed_ips', $apiAllowedIps) }}</textarea>
            </div>
        </div>

        {{-- ============================================ --}}
        {{-- TAB: Web Standards --}}
        {{-- ============================================ --}}
        <div class="tab-panel hidden bg-card border border-card-border rounded p-5 space-y-6" data-tab="webstandards" role="tabpanel">
            {{-- robots.txt --}}
            <div class="border-b border-card-border pb-6">
                <label class="inline-flex items-center gap-2 text-sm text-copy mb-2">
                    <input type="hidden" name="robots_enabled" value="0">
                    <input type="checkbox" name="robots_enabled" value="1"
                           @checked($robotsEnabled)>
                    {{ __('messages.settings_robots_enabled') }}
                </label>
                <p class="mt-1 text-xs text-muted mb-3">{{ __('messages.settings_robots_enabled_help') }}</p>

                <label class="block text-sm text-muted mb-1">{{ __('messages.settings_robots_content') }}</label>
                <p class="text-xs text-muted mb-2">{{ __('messages.settings_robots_content_help') }}</p>
                <textarea name="robots_content" rows="6" maxlength="5000"
                          class="w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy font-mono">{{ old('robots_content', $robotsContent) }}</textarea>
            </div>

            {{-- llms.txt --}}
            <div class="border-b border-card-border pb-6">
                <label class="inline-flex items-center gap-2 text-sm text-copy">
                    <input type="hidden" name="llms_enabled" value="0">
                    <input type="checkbox" name="llms_enabled" value="1"
                           @checked($llmsEnabled)>
                    {{ __('messages.settings_llms_enabled') }}
                </label>
                <p class="mt-1 text-xs text-muted">{{ __('messages.settings_llms_enabled_help') }}</p>
            </div>

            {{-- llms-full.txt --}}
            <div class="border-b border-card-border pb-6">
                <label class="inline-flex items-center gap-2 text-sm text-copy">
                    <input type="hidden" name="llms_full_enabled" value="0">
                    <input type="checkbox" name="llms_full_enabled" value="1"
                           @checked($llmsFullEnabled)>
                    {{ __('messages.settings_llms_full_enabled') }}
                </label>
                <p class="mt-1 text-xs text-muted">{{ __('messages.settings_llms_full_enabled_help') }}</p>
            </div>

            {{-- sitemap.xml --}}
            <div>
                <label class="inline-flex items-center gap-2 text-sm text-copy">
                    <input type="hidden" name="sitemap_enabled" value="0">
                    <input type="checkbox" name="sitemap_enabled" value="1"
                           @checked($sitemapEnabled)>
                    {{ __('messages.settings_sitemap_enabled') }}
                </label>
                <p class="mt-1 text-xs text-muted">{{ __('messages.settings_sitemap_enabled_help') }}</p>
            </div>
        </div>

        {{-- ============================================ --}}
        {{-- TAB: Users --}}
        {{-- ============================================ --}}
        <div class="tab-panel hidden bg-card border border-card-border rounded p-5 space-y-6" data-tab="users" role="tabpanel">
            <div>
                <label class="block text-sm font-semibold text-copy mb-2">{{ __('messages.settings_users_heading') }}</label>
                <p class="text-xs text-muted mb-4">{{ __('messages.settings_users_help') }}</p>

                @if($users->isEmpty())
                    <p class="text-sm text-muted italic">{{ __('messages.settings_users_empty') }}</p>
                @else
                    <div class="space-y-3">
                        @foreach($users as $user)
                            <div class="grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-2 items-start border border-card-border rounded p-3">
                                <div>
                                    <label class="block text-xs text-muted mb-1">{{ __('messages.settings_users_email') }}</label>
                                    <input type="email" name="users[{{ $user->id }}][email]"
                                           value="{{ old("users.{$user->id}.email", $user->email) }}"
                                           class="w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy">
                                </div>
                                <div>
                                    <label class="block text-xs text-muted mb-1">{{ __('messages.settings_users_display_name') }}</label>
                                    <input type="text" name="users[{{ $user->id }}][display_name]"
                                           value="{{ old("users.{$user->id}.display_name", $user->display_name) }}"
                                           class="w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy">
                                </div>
                                <label class="inline-flex items-center gap-2 text-xs text-red-400 sm:mt-7">
                                    <input type="hidden" name="users[{{ $user->id }}][remove]" value="0">
                                    <input type="checkbox" name="users[{{ $user->id }}][remove]" value="1">
                                    {{ __('messages.settings_users_remove') }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-5 border-t border-card-border pt-4">
                    <p class="text-sm font-semibold text-copy mb-2">{{ __('messages.settings_users_add') }}</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs text-muted mb-1">{{ __('messages.settings_users_email') }}</label>
                            <input type="email" name="new_user_email" value="{{ old('new_user_email') }}"
                                   class="w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy">
                        </div>
                        <div>
                            <label class="block text-xs text-muted mb-1">{{ __('messages.settings_users_display_name') }}</label>
                            <input type="text" name="new_user_display_name" value="{{ old('new_user_display_name') }}"
                                   class="w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Submit button --}}
        <div class="mt-6">
            <button type="submit" class="bg-accent hover:bg-accent-hover text-white font-medium px-4 py-2 rounded">
                {{ __('messages.settings_save') }}
            </button>
        </div>
    </form>

    {{-- Tab switching JS --}}
    <script>
        (function () {
            const STORAGE_KEY = 'admin_settings_active_tab';
            const tabs = document.querySelectorAll('.tab-btn');
            const panels = document.querySelectorAll('.tab-panel');

            function activateTab(tabName) {
                tabs.forEach(btn => {
                    const isActive = btn.dataset.tab === tabName;
                    btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
                    if (isActive) {
                        btn.classList.add('bg-accent', 'border-accent', 'text-white');
                        btn.classList.remove('bg-transparent', 'border-card-border', 'text-muted');
                    } else {
                        btn.classList.remove('bg-accent', 'border-accent', 'text-white');
                        btn.classList.add('bg-transparent', 'border-card-border', 'text-muted');
                    }
                });

                panels.forEach(panel => {
                    if (panel.dataset.tab === tabName) {
                        panel.classList.remove('hidden');
                    } else {
                        panel.classList.add('hidden');
                    }
                });

                sessionStorage.setItem(STORAGE_KEY, tabName);
            }

            // Click handlers
            tabs.forEach(btn => {
                btn.addEventListener('click', function () {
                    activateTab(this.dataset.tab);
                });
            });

            // Determine initial tab
            let activeTab = sessionStorage.getItem(STORAGE_KEY);

            @if($errors->any())
                // On validation error, find the first tab containing an error
                let errorTab = null;
                panels.forEach(panel => {
                    if (!errorTab && panel.querySelector('.border-red-700, [class*="error"], input:invalid')) {
                        errorTab = panel.dataset.tab;
                    }
                });
                if (errorTab) {
                    activeTab = errorTab;
                }
            @endif

            // Fall back to first tab if stored tab doesn't exist
            const validTabs = Array.from(tabs).map(t => t.dataset.tab);
            if (!activeTab || !validTabs.includes(activeTab)) {
                activeTab = validTabs[0];
            }

            activateTab(activeTab);
        })();
    </script>

    {{-- AI Translate for about & footer fields --}}
    <script>
        (function () {
            const fieldSelectors = {
                'about': '.about-field',
                'footer_text': '.footer-text-field',
                'footer_html': '.footer-html-field',
            };

            document.querySelectorAll('.ai-translate-link').forEach(btn => {
                btn.addEventListener('click', async function () {
                    const targetName = this.dataset.target;
                    const targetLocale = this.dataset.targetLocale;
                    const sourceLocale = this.dataset.sourceLocale;
                    const fieldType = this.dataset.fieldType;
                    const targetField = document.querySelector(`[name="${targetName}"]`);
                    const statusEl = this.parentElement.querySelector('.ai-translate-status');

                    // Find source field by matching locale and field type
                    const srcSelector = fieldSelectors[fieldType] || '.about-field';
                    const sourceField = document.querySelector(`${srcSelector}[data-locale="${sourceLocale}"]`);
                    const sourceText = sourceField?.value.trim() || '';

                    if (!sourceText) {
                        const localeNames = @json(\App\Support\Locales::supported());
                        const srcName = localeNames[sourceLocale]?.name || sourceLocale;
                        alert(@json(__('messages.translate_no_source_for')).replace(':locale', srcName));
                        return;
                    }

                    this.classList.add('hidden');
                    if (statusEl) {
                        statusEl.classList.remove('hidden');
                        statusEl.textContent = @json(__('messages.translating'));
                    }

                    try {
                        const resp = await fetch('{{ route('admin.translate') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                text: sourceText,
                                target_locale: targetLocale,
                            }),
                        });

                        const data = await resp.json();
                        if (data.translated_text) {
                            targetField.value = data.translated_text;
                            if (statusEl) statusEl.textContent = @json(__('messages.translate_done'));
                        } else {
                            if (statusEl) statusEl.textContent = data.error || @json(__('messages.translate_error'));
                        }
                    } catch (e) {
                        const msg = e.name === 'TypeError' || (e.message && e.message.includes('fetch'))
                            ? @json(__('messages.translate_error_network'))
                            : e.name === 'AbortError'
                                ? @json(__('messages.translate_error_timeout'))
                                : @json(__('messages.translate_error'));
                        if (statusEl) statusEl.textContent = msg;
                    }

                    this.classList.remove('hidden');
                    setTimeout(() => {
                        if (statusEl) {
                            statusEl.classList.add('hidden');
                            statusEl.textContent = '';
                        }
                    }, 5000);
                });
            });
        })();
    </script>

    {{-- Copy API token --}}
    <script>
        (function () {
            const copyBtn = document.getElementById('copy-api-token');
            if (copyBtn) {
                copyBtn.addEventListener('click', function () {
                    const token = this.dataset.token;
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(token).then(() => {
                            const original = this.textContent;
                            this.textContent = '{{ __('messages.settings_api_token_copied') }}';
                            setTimeout(() => { this.textContent = original; }, 2000);
                        });
                    }
                });
            }
        })();
    </script>
@endsection
