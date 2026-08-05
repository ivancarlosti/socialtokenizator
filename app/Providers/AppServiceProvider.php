<?php

namespace App\Providers;

use App\Auth\AuthMethodResolver;
use App\Models\Setting;
use App\Support\Locales;
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
            $logoKey    = $this->safeSetting('site_logo_key');
            $faviconKey = $this->safeSetting('site_favicon_key');

            $footerLinks = [];
            for ($i = 1; $i <= 3; $i++) {
                $label = $this->safeSetting("footer_link_{$i}_label");
                $url   = $this->safeSetting("footer_link_{$i}_url");
                if ($label && $url) {
                    $footerLinks[] = ['label' => $label, 'url' => $url];
                }
            }

            $locale        = app()->getLocale();
            $siteTitle     = $this->safeSetting("site_title_{$locale}") ?: config('app.name');
            $siteSubtitle  = $this->safeSetting("site_subtitle_{$locale}");
            $footerHtml    = $this->safeSetting("footer_html_{$locale}");
            $postPathPrefix  = $this->safeSetting('post_path_prefix') ?: 'p';
            $defaultTheme    = $this->safeSetting('default_theme') ?: 'dark';
            $hideTitleSection = (bool) $this->safeSetting('hide_title_section');
            $hideFilterLabel  = (bool) $this->safeSetting('hide_filter_label');

            // Compute context-aware feed URLs (per format) for the header icon and <link> tags
            $feedQueryParams = ['lang' => $locale];
            $routeName = request()->route()?->getName();
            if ($routeName === 'home') {
                $cat = request()->query('category');
                $tag = request()->query('tag');
                if ($cat && is_string($cat) && $cat !== '') {
                    $feedQueryParams['category'] = $cat;
                } elseif ($tag && is_string($tag) && $tag !== '') {
                    $feedQueryParams['tag'] = $tag;
                }
            }

            $feedAtomUrl = route('feed.atom', $feedQueryParams);
            $feedRssUrl  = route('feed.rss',  $feedQueryParams);
            $feedJsonUrl = route('feed.json', $feedQueryParams);

            $view->with([
                'authMethod'       => AuthMethodResolver::current(),
                'isAdmin'          => AuthMethodResolver::isAdmin(),
                'siteLogoUrl'      => Setting::publicUrl($logoKey),
                'siteFaviconUrl'   => Setting::publicUrl($faviconKey),
                'siteTitle'        => $siteTitle,
                'siteSubtitle'     => $siteSubtitle,
                'footerHtml'       => $footerHtml,
                'postPathPrefix'   => $postPathPrefix,
                'hideTitleSection' => $hideTitleSection,
                'hideFilterLabel'  => $hideFilterLabel,
                'currentLocale'    => $locale,
                'supportedLocales' => Locales::supported(),
                'footerLinks'      => $footerLinks,
                'feedUrl'          => $feedAtomUrl,
                'feedAtomUrl'      => $feedAtomUrl,
                'feedRssUrl'       => $feedRssUrl,
                'feedJsonUrl'      => $feedJsonUrl,
                'defaultTheme'     => $defaultTheme,
            ]);
        });
    }

    /**
     * Read a setting safely. Returns null if the settings table doesn't exist
     * yet (e.g. before migrations run) or any other read error occurs.
     */
    private function safeSetting(string $key): ?string
    {
        try {
            $value = Setting::get($key);
            return is_string($value) ? $value : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
