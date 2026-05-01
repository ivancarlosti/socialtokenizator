<?php

namespace App\Http\Controllers\Auth;

use App\Auth\AuthMethodResolver;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class AccountAuthController extends Controller
{
    public function showLogin()
    {
        if (! AuthMethodResolver::isAccount()) abort(404);
        return view('auth.login', [
            'recaptchaSiteKey' => config('auth_method.account.recaptcha_site_key'),
        ]);
    }

    public function login(Request $request)
    {
        if (! AuthMethodResolver::isAccount()) abort(404);

        $data = $request->validate([
            'login' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'max:255'],
            'g-recaptcha-response' => ['nullable', 'string'],
        ]);

        $this->verifyRecaptcha($request);

        $expectedLogin = (string) config('auth_method.account.login');
        $expectedPassword = (string) config('auth_method.account.password');

        if ($expectedLogin === '' || $expectedPassword === '') {
            throw ValidationException::withMessages([
                'login' => 'Account credentials are not configured on the server.',
            ]);
        }

        $loginOk = hash_equals($expectedLogin, $data['login']);

        $info = password_get_info($expectedPassword);
        $passwordOk = ($info['algo'] ?? null)
            ? password_verify($data['password'], $expectedPassword)
            : hash_equals($expectedPassword, $data['password']);

        if (! $loginOk || ! $passwordOk) {
            throw ValidationException::withMessages([
                'login' => 'Invalid credentials.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('admin', true);
        $request->session()->put('admin_method', 'account');
        $request->session()->put('admin_login', $data['login']);

        return redirect()->intended(route('admin.upload.create'));
    }

    public function logout(Request $request)
    {
        $request->session()->forget(['admin', 'admin_method', 'admin_login']);
        $request->session()->regenerate();
        return redirect()->route('home');
    }

    private function verifyRecaptcha(Request $request): void
    {
        $secret = (string) config('auth_method.account.recaptcha_secret');
        if ($secret === '') return;

        $token = (string) $request->input('g-recaptcha-response');
        if ($token === '') {
            throw ValidationException::withMessages([
                'login' => 'Please complete the CAPTCHA.',
            ]);
        }

        $resp = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $request->ip(),
        ])->json();

        if (! ($resp['success'] ?? false)) {
            throw ValidationException::withMessages([
                'login' => 'CAPTCHA validation failed.',
            ]);
        }
    }
}
