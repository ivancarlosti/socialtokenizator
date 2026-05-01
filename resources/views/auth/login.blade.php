@extends('layouts.app')

@section('title', 'Login — '.config('app.name'))

@section('meta')
    @if($recaptchaSiteKey)
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endif
@endsection

@section('content')
    <div class="max-w-sm mx-auto bg-neutral-900 border border-neutral-800 rounded p-6">
        <h1 class="text-xl font-semibold text-white">Admin login</h1>

        @if($errors->any())
            <div class="mt-3 bg-red-900/30 border border-red-700 text-red-200 rounded px-3 py-2 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="post" action="{{ route('auth.login') }}" class="mt-4 space-y-3">
            @csrf
            <div>
                <label class="block text-sm text-neutral-300 mb-1">Login</label>
                <input type="text" name="login" autocomplete="username" required value="{{ old('login') }}"
                       class="w-full bg-neutral-950 border border-neutral-700 rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm text-neutral-300 mb-1">Password</label>
                <input type="password" name="password" autocomplete="current-password" required
                       class="w-full bg-neutral-950 border border-neutral-700 rounded px-3 py-2 text-sm">
            </div>

            @if($recaptchaSiteKey)
                <div class="g-recaptcha" data-sitekey="{{ $recaptchaSiteKey }}"></div>
            @endif

            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-medium px-4 py-2 rounded">
                Sign in
            </button>
        </form>
    </div>
@endsection
