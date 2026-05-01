<!doctype html>
<html lang="{{ str_replace('_', '-', $currentLocale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>

    @if($siteFaviconUrl)
        <link rel="icon" href="{{ $siteFaviconUrl }}">
        <link rel="shortcut icon" href="{{ $siteFaviconUrl }}">
    @endif

    @hasSection('meta')
        @yield('meta')
    @else
        @php
            $defaultTitle = trim(View::getSection('title') ?: config('app.name'));
            $defaultDesc  = __('messages.shared_via', ['app' => config('app.name')]);
            $defaultUrl   = url()->current();
            $defaultImg   = $siteLogoUrl;
        @endphp
        <meta name="description" content="{{ $defaultDesc }}">
        <meta property="og:title" content="{{ $defaultTitle }}">
        <meta property="og:description" content="{{ $defaultDesc }}">
        <meta property="og:url" content="{{ $defaultUrl }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name') }}">
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

    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: #0b0b0d; color: #e5e7eb; }
        a { color: #93c5fd; }
        a:hover { color: #bfdbfe; }
    </style>
</head>
<body class="min-h-screen">
    <header class="border-b border-neutral-800">
        <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between gap-4 flex-wrap">
            <a href="{{ route('home') }}" class="flex items-center gap-2 text-xl font-semibold tracking-tight text-white">
                @if($siteLogoUrl)
                    <img src="{{ $siteLogoUrl }}" alt="{{ config('app.name') }}" class="h-8 w-auto">
                @else
                    {{ config('app.name') }}
                @endif
            </a>
            <nav class="flex items-center gap-3 text-sm flex-wrap">
                <form action="{{ route('search') }}" method="get" class="flex">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('messages.search_placeholder') }}"
                           class="bg-neutral-900 border border-neutral-700 rounded-l px-3 py-1.5 focus:outline-none focus:border-neutral-500">
                    <button type="submit" class="bg-neutral-700 hover:bg-neutral-600 text-white px-3 py-1.5 rounded-r">{{ __('messages.search_go') }}</button>
                </form>

                {{-- Language switcher --}}
                <div class="flex items-center gap-1" aria-label="{{ __('messages.language') }}">
                    @foreach($supportedLocales as $code => $info)
                        @php $isActive = $code === $currentLocale; @endphp
                        <a href="{{ route('locale.switch', ['locale' => $code]) }}"
                           title="{{ $info['name'] }}"
                           class="inline-flex items-center justify-center rounded-sm overflow-hidden border {{ $isActive ? 'border-emerald-400 ring-1 ring-emerald-400' : 'border-neutral-700 opacity-70 hover:opacity-100' }}">
                            <img src="https://flagcdn.com/w40/{{ $info['flag'] }}.png"
                                 srcset="https://flagcdn.com/w80/{{ $info['flag'] }}.png 2x"
                                 alt="{{ $info['name'] }}" width="24" height="18" class="block">
                        </a>
                    @endforeach
                </div>

                @if($isAdmin)
                    <a href="{{ route('admin.upload.create') }}" class="text-emerald-400">{{ __('messages.upload') }}</a>
                    <a href="{{ route('admin.settings.edit') }}" class="text-emerald-400">{{ __('messages.settings') }}</a>
                    @if(\Illuminate\Support\Facades\Route::has('auth.logout'))
                        <form method="post" action="{{ route('auth.logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-neutral-400 hover:text-white">{{ __('messages.logout') }}</button>
                        </form>
                    @endif
                @elseif($authMethod !== 'none')
                    @if($authMethod === 'account' && \Illuminate\Support\Facades\Route::has('auth.login.show'))
                        <a href="{{ route('auth.login.show') }}" class="text-neutral-400 hover:text-white">{{ __('messages.login') }}</a>
                    @elseif($authMethod === 'keycloak' && \Illuminate\Support\Facades\Route::has('auth.keycloak.redirect'))
                        <a href="{{ route('auth.keycloak.redirect') }}" class="text-neutral-400 hover:text-white">{{ __('messages.login') }}</a>
                    @endif
                @endif
            </nav>
        </div>
    </header>

    @if(session('status'))
        <div class="max-w-5xl mx-auto px-4 mt-4">
            <div class="bg-emerald-900/30 border border-emerald-700 text-emerald-200 rounded px-4 py-2 text-sm">
                {{ session('status') }}
            </div>
        </div>
    @endif

    <main class="max-w-5xl mx-auto px-4 py-8">
        @yield('content')
    </main>

    <footer class="border-t border-neutral-800 mt-12">
        <div class="max-w-5xl mx-auto px-4 py-6 text-xs text-neutral-500 flex justify-between">
            <span>{{ config('app.name') }}</span>
            <span>auth: {{ $authMethod }}</span>
        </div>
    </footer>
</body>
</html>
