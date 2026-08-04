<!doctype html>
<html lang="{{ str_replace('_', '-', $currentLocale) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $siteTitle)</title>

    @if($siteFaviconUrl)
        <link rel="icon" href="{{ $siteFaviconUrl }}">
        <link rel="shortcut icon" href="{{ $siteFaviconUrl }}">
    @endif

    @hasSection('meta')
        @yield('meta')
    @else
        @php
            $defaultTitle = trim(View::getSection('title') ?: $siteTitle);
            $defaultDesc  = $siteSubtitle ?: __('messages.shared_via', ['app' => $siteTitle]);
            $defaultUrl   = url()->current();
            $defaultImg   = $siteLogoUrl;
        @endphp
        <meta name="description" content="{{ $defaultDesc }}">
        <meta property="og:title" content="{{ $defaultTitle }}">
        <meta property="og:description" content="{{ $defaultDesc }}">
        <meta property="og:url" content="{{ $defaultUrl }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $siteTitle }}">
        @if($defaultImg)
            <meta property="og:image" content="{{ $defaultImg }}">
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:image" content="{{ $defaultImg }}">
        @else
            <meta name="twitter:card" content="summary">
        @endif
        <meta name="twitter:title" content="{{ $defaultTitle }}">
        <meta name="twitter:description" content="{{ $defaultDesc }}">
        <link rel="canonical" href="{{ $defaultUrl }}">
    @endif

    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) ?: '0' }}">
</head>
<body class="min-h-screen">
    <header class="border-b" style="background: var(--color-header-bg); border-color: var(--color-header-border);">
        <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between gap-4 flex-wrap">
            <a href="{{ route('home') }}" class="flex items-center gap-2 text-xl font-semibold tracking-tight text-copy">
                @if($siteLogoUrl)
                    <img src="{{ $siteLogoUrl }}" alt="{{ $siteTitle }}" class="h-8 w-auto">
                @else
                    {{ $siteTitle }}
                @endif
            </a>
            <nav class="flex items-center gap-3 text-sm flex-wrap">
                <form action="{{ route('search') }}" method="get" class="flex">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('messages.search_placeholder') }}"
                           class="bg-input border border-input-border rounded-l px-3 py-1.5 text-copy focus:outline-none focus:border-neutral-500" style="background: var(--color-input); border-color: var(--color-input-border); color: var(--color-copy);">
                    <button type="submit" class="bg-neutral-700 hover:bg-neutral-600 text-white px-3 py-1.5 rounded-r">{{ __('messages.search_go') }}</button>
                </form>

                {{-- Theme toggle --}}
                <button type="button" id="theme-toggle"
                        class="p-1.5 rounded border border-card-border text-muted hover:text-copy"
                        style="border-color: var(--color-card-border);"
                        title="{{ __('messages.theme_toggle_light') }}">
                    <svg id="theme-icon-sun" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 hidden">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                    </svg>
                    <svg id="theme-icon-moon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                    </svg>
                </button>

                {{-- Language switcher --}}
                <div class="flex items-center gap-1" aria-label="{{ __('messages.language') }}">
                    @foreach($supportedLocales as $code => $info)
                        @php $isActive = $code === $currentLocale; @endphp
                        <a href="{{ route('locale.switch', ['locale' => $code]) }}"
                           title="{{ $info['name'] }}"
                           class="inline-flex items-center justify-center rounded overflow-hidden transition-opacity {{ $isActive ? 'opacity-100' : 'opacity-50 hover:opacity-100' }}">
                            <img src="https://flagcdn.com/w40/{{ $info['flag'] }}.png"
                                 srcset="https://flagcdn.com/w80/{{ $info['flag'] }}.png 2x"
                                 alt="{{ $info['name'] }}" class="block w-[34px] h-[23px]">
                        </a>
                    @endforeach
                </div>

                {{-- Feed icon --}}
                <a href="{{ $feedUrl }}"
                   class="p-1.5 rounded border border-card-border text-muted hover:text-copy"
                   style="border-color: var(--color-card-border);"
                   title="{{ __('messages.feed_atom') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                        <path d="M6.18 15.64a2.18 2.18 0 0 1 2.18 2.18C8.36 19 7.38 20 6.18 20C5 20 4 19 4 17.82a2.18 2.18 0 0 1 2.18-2.18M4 4.44A15.56 15.56 0 0 1 19.56 20h-2.83A12.73 12.73 0 0 0 4 7.27V4.44m0 5.66a9.9 9.9 0 0 1 9.9 9.9h-2.83A7.07 7.07 0 0 0 4 12.93V10.1Z"/>
                    </svg>
                </a>

                @if($isAdmin)
                    <a href="{{ route('admin.upload.create') }}" class="text-accent">{{ __('messages.upload') }}</a>
                    <a href="{{ route('admin.categories.index') }}" class="text-accent">{{ __('messages.admin_categories') }}</a>
                    <a href="{{ route('admin.settings.edit') }}" class="text-accent">{{ __('messages.settings') }}</a>
                    @if(\Illuminate\Support\Facades\Route::has('auth.logout'))
                        <form method="post" action="{{ route('auth.logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-neutral-400 hover:text-white">{{ __('messages.logout') }}</button>
                        </form>
                    @endif
                @elseif($authMethod !== 'none')
                    @if($authMethod === 'account' && \Illuminate\Support\Facades\Route::has('auth.login.show'))
                        <a href="{{ route('auth.login.show') }}" class="text-muted hover:text-copy">{{ __('messages.login') }}</a>
                    @elseif($authMethod === 'keycloak' && \Illuminate\Support\Facades\Route::has('auth.keycloak.redirect'))
                        <a href="{{ route('auth.keycloak.redirect') }}" class="text-muted hover:text-copy">{{ __('messages.login') }}</a>
                    @endif
                @endif
            </nav>
        </div>
    </header>

    @if(session('status'))
        <div class="max-w-5xl mx-auto px-4 mt-4">
            <div class="rounded px-4 py-2 text-sm" style="background: var(--color-status-bg); border: 1px solid var(--color-status-border); color: var(--color-status-text);">
                {{ session('status') }}
            </div>
        </div>
    @endif

    <main class="max-w-5xl mx-auto px-4 py-8">
        @yield('content')
    </main>

    <footer class="border-t mt-12" style="border-color: var(--color-footer-border);">
        <div class="max-w-5xl mx-auto px-4 py-6 text-xs text-muted flex justify-between gap-4 flex-wrap">
            <span>{{ $siteTitle }}</span>
            <div class="flex items-center gap-4 flex-wrap">
                @if($footerHtml)
                    <div class="footer-html-content">{!! $footerHtml !!}</div>
                @endif
                @if(!empty($footerLinks))
                    <nav class="flex items-center gap-4">
                        @foreach($footerLinks as $link)
                            <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer"
                               class="text-muted hover:text-copy">{{ $link['label'] }}</a>
                        @endforeach
                    </nav>
                @endif
            </div>
        </div>
    </footer>

    <script>
        (function () {
            const html = document.documentElement;
            const toggle = document.getElementById('theme-toggle');
            const sunIcon = document.getElementById('theme-icon-sun');
            const moonIcon = document.getElementById('theme-icon-moon');

            function setTheme(dark) {
                if (dark) {
                    html.classList.add('dark');
                    sunIcon.classList.remove('hidden');
                    moonIcon.classList.add('hidden');
                    toggle.title = @json(__('messages.theme_toggle_light'));
                } else {
                    html.classList.remove('dark');
                    sunIcon.classList.add('hidden');
                    moonIcon.classList.remove('hidden');
                    toggle.title = @json(__('messages.theme_toggle_dark'));
                }
            }

            const stored = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const dark = stored ? stored === 'dark' : prefersDark;
            setTheme(dark);

            toggle.addEventListener('click', function () {
                const isDark = html.classList.contains('dark');
                setTheme(!isDark);
                localStorage.setItem('theme', !isDark ? 'dark' : 'light');
            });
        })();
    </script>
</body>
</html>
