<?php

namespace App\Http\Controllers\Auth;

use App\Auth\AuthMethodResolver;
use App\Http\Controllers\Controller;
use App\Support\UserAuthorizer;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class KeycloakAuthController extends Controller
{
    public function redirect()
    {
        if (! AuthMethodResolver::isKeycloak()) abort(404);

        return Socialite::driver('keycloak')
            ->scopes(['openid', 'email', 'profile'])
            ->redirect();
    }

    public function callback(Request $request)
    {
        if (! AuthMethodResolver::isKeycloak()) abort(404);

        $user = Socialite::driver('keycloak')->user();

        $email = UserAuthorizer::normalizeEmail((string) ($user->getEmail() ?? ''));

        if (! UserAuthorizer::isKeycloakEmailAllowed($email)) {
            abort(403, __('messages.auth_user_not_authorized'));
        }

        $authorizedUser = UserAuthorizer::authorizeLogin($email);
        if (! $authorizedUser) {
            abort(403, __('messages.auth_user_not_registered'));
        }

        $request->session()->regenerate();
        $request->session()->put('admin', true);
        $request->session()->put('admin_method', 'keycloak');
        $request->session()->put('admin_email', $authorizedUser->email);
        $request->session()->put('admin_user_id', $authorizedUser->id);
        $request->session()->put('admin_id_token', $user->accessTokenResponseBody['id_token'] ?? null);

        return redirect()->intended(route('admin.upload.create'));
    }

    public function logout(Request $request)
    {
        $idToken = $request->session()->get('admin_id_token');
        $request->session()->forget(['admin', 'admin_method', 'admin_email', 'admin_user_id', 'admin_id_token']);
        $request->session()->regenerate();

        $base = rtrim((string) config('auth_method.keycloak.base_url'), '/');
        $realm = (string) config('auth_method.keycloak.realm');
        if ($base === '' || $realm === '') {
            return redirect()->route('home');
        }

        $logoutUrl = $base.'/realms/'.$realm.'/protocol/openid-connect/logout?'
            .http_build_query([
                'post_logout_redirect_uri' => route('home'),
                'client_id' => config('auth_method.keycloak.client_id'),
                'id_token_hint' => $idToken,
            ]);

        return redirect()->away($logoutUrl);
    }
}
