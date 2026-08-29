<?php

namespace App\Http\Controllers\Auth;

use App\Auth\AuthMethodResolver;
use App\Http\Controllers\Controller;
use App\Support\UserAuthorizer;
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
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'g-recaptcha-response' => ['nullable', 'string'],
        ]);

        $this->verifyRecaptcha($request);

        $email = UserAuthorizer::normalizeEmail((string) $data['email']);

        if (! UserAuthorizer::accountCredentialsMatch($email, (string) $data['password'])) {
            throw ValidationException::withMessages([
                'email' => __('messages.auth_invalid_credentials'),
            ]);
        }

        $user = UserAuthorizer::authorizeLogin($email);
        if (! $user) {
            throw ValidationException::withMessages([
                'email' => __('messages.auth_user_not_registered'),
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('admin', true);
        $request->session()->put('admin_method', 'account');
        $request->session()->put('admin_email', $user->email);
        $request->session()->put('admin_user_id', $user->id);

        return redirect()->intended(route('admin.upload.create'));
    }

    public function logout(Request $request)
    {
        $request->session()->forget(['admin', 'admin_method', 'admin_email', 'admin_user_id']);
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
                'email' => __('messages.auth_captcha_required'),
            ]);
        }

        $resp = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $request->ip(),
        ])->json();

        if (! ($resp['success'] ?? false)) {
            throw ValidationException::withMessages([
                'email' => __('messages.auth_captcha_failed'),
            ]);
        }
    }
}
