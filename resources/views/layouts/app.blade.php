<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    @yield('meta')
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: #0b0b0d; color: #e5e7eb; }
        a { color: #93c5fd; }
        a:hover { color: #bfdbfe; }
    </style>
</head>
<body class="min-h-screen">
    <header class="border-b border-neutral-800">
        <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="{{ route('home') }}" class="text-xl font-semibold tracking-tight text-white">
                {{ config('app.name') }}
            </a>
            <nav class="flex items-center gap-4 text-sm">
                <form action="{{ route('search') }}" method="get" class="flex">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search…"
                           class="bg-neutral-900 border border-neutral-700 rounded-l px-3 py-1.5 focus:outline-none focus:border-neutral-500">
                    <button type="submit" class="bg-neutral-700 hover:bg-neutral-600 text-white px-3 py-1.5 rounded-r">Go</button>
                </form>
                @if($isAdmin)
                    <a href="{{ route('admin.upload.create') }}" class="text-emerald-400">Upload</a>
                    @if(\Illuminate\Support\Facades\Route::has('auth.logout'))
                        <form method="post" action="{{ route('auth.logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-neutral-400 hover:text-white">Logout</button>
                        </form>
                    @endif
                @elseif($authMethod !== 'none')
                    @if($authMethod === 'account' && \Illuminate\Support\Facades\Route::has('auth.login.show'))
                        <a href="{{ route('auth.login.show') }}" class="text-neutral-400 hover:text-white">Login</a>
                    @elseif($authMethod === 'keycloak' && \Illuminate\Support\Facades\Route::has('auth.keycloak.redirect'))
                        <a href="{{ route('auth.keycloak.redirect') }}" class="text-neutral-400 hover:text-white">Login</a>
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
