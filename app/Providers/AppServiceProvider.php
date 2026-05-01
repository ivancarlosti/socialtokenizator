<?php

namespace App\Providers;

use App\Auth\AuthMethodResolver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Register Keycloak Socialite provider only when active
        if (AuthMethodResolver::isKeycloak()) {
            $this->app['events']->listen(
                SocialiteWasCalled::class,
                'SocialiteProviders\\Keycloak\\KeycloakExtendSocialite@handle'
            );
        }

        View::composer('*', function ($view) {
            $view->with([
                'authMethod' => AuthMethodResolver::current(),
                'isAdmin' => AuthMethodResolver::isAdmin(),
            ]);
        });
    }
}
