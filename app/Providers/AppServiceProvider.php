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
            $faviconSvgKey = $this->safeSetting('site_favicon_svg_key');
            $favicon32Key  = $this->safeSetting('site_favicon_32_key');
            $favicon180Key = $this->safeSetting('site_favicon_180_key');
            $favicon192Key = $this->safeSetting('site_favicon_192_key');
            $favicon512Key = $this->safeSetting('site_favicon_512_key');

            $locale        = app()->getLocale();
            $siteTitle     = $this->safeSetting("site_title_{$locale}") ?: config('app.name');
            $siteSubtitle  = $this->safeSetting("site_subtitle_{$locale}");
            $footerText    = $this->safeSetting("footer_text_{$locale}");
            $footerHtml    = $this->safeSetting("footer_html_{$locale}");
            $postPathPrefix  = $this->safeSetting('post_path_prefix') ?: 'p';
            $defaultTheme    = $this->safeSetting('default_theme') ?: 'dark';
            $hideTitleSection = (bool) $this->safeSetting('hide_title_section');
            $hideFilterLabel  = (bool) $this->safeSetting('hide_filter_label');
            $showPostAuthor   = (bool) $this->safeSetting('show_post_author');
            $showPostPublished = (bool) $this->safeSetting('show_post_published');
            $showPostUpdated   = (bool) $this->safeSetting('show_post_updated');
            $showPostAuthorInList    = (bool) $this->safeSetting('show_post_author_in_list');
            $showPostPublishedInList = (bool) $this->safeSetting('show_post_published_in_list');
            $showPostUpdatedInList   = (bool) $this->safeSetting('show_post_updated_in_list');
            $showPostDescriptionInList = (bool) $this->safeSetting('show_post_description_in_list');
            $postDescriptionInListMode = $this->safeSetting('post_description_in_list_mode') ?: 'excerpt';
            $postDescriptionInListLength = (int) ($this->safeSetting('post_description_in_list_length') ?: 300);

            $customHead = $this->safeSetting('custom_head');
            $customCss  = $this->safeSetting('custom_css');
            $customJs   = $this->safeSetting('custom_js');

            $twitterSite      = $this->safeSetting('twitter_site');
            if ($twitterSite !== null && $twitterSite !== '') {
                $twitterSite = '@' . ltrim($twitterSite, '@');
            }
            $themeColorLight  = $this->safeSetting('theme_color_light') ?: '#f9fafb';
            $themeColorDark   = $this->safeSetting('theme_color_dark') ?: '#111827';
            $ogLocale         = Locales::default();
            $ogLocaleAlternates = array_values(array_diff(array_keys(Locales::supported()), [$ogLocale]));

            $siteTimezone = $this->safeSetting('site_timezone') ?: config('app.timezone');
            if (! in_array($siteTimezone, timezone_identifiers_list(), true)) {
                $siteTimezone = 'UTC';
            }

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
                'siteFaviconSvgUrl' => Setting::publicUrl($faviconSvgKey),
                'siteFavicon32Url'  => Setting::publicUrl($favicon32Key),
                'appleTouchIconUrl' => Setting::publicUrl($favicon180Key),
                'siteFavicon192Url' => Setting::publicUrl($favicon192Key),
                'siteFavicon512Url' => Setting::publicUrl($favicon512Key),
                'manifestUrl'       => url('/site.webmanifest'),
                'twitterSite'       => $twitterSite,
                'themeColorLight'   => $themeColorLight,
                'themeColorDark'    => $themeColorDark,
                'ogLocale'          => $ogLocale,
                'ogLocaleAlternates'=> $ogLocaleAlternates,
                'siteTitle'        => $siteTitle,
                'siteSubtitle'     => $siteSubtitle,
                'footerText'       => $footerText,
                'footerHtml'       => $footerHtml,
                'postPathPrefix'   => $postPathPrefix,
                'hideTitleSection' => $hideTitleSection,
                'hideFilterLabel'  => $hideFilterLabel,
                'showPostAuthor'    => $showPostAuthor,
                'showPostPublished' => $showPostPublished,
                'showPostUpdated'   => $showPostUpdated,
                'showPostAuthorInList'    => $showPostAuthorInList,
                'showPostPublishedInList' => $showPostPublishedInList,
                'showPostUpdatedInList'   => $showPostUpdatedInList,
                'showPostDescriptionInList' => $showPostDescriptionInList,
                'postDescriptionInListMode' => $postDescriptionInListMode,
                'postDescriptionInListLength' => $postDescriptionInListLength,
                'siteTimezone'      => $siteTimezone,
                'currentLocale'    => $locale,
                'supportedLocales' => Locales::supported(),
                'feedUrl'          => $feedAtomUrl,
                'feedAtomUrl'      => $feedAtomUrl,
                'feedRssUrl'       => $feedRssUrl,
                'feedJsonUrl'      => $feedJsonUrl,
                'defaultTheme'     => $defaultTheme,
                'customHead'       => $customHead,
                'customCss'        => $customCss,
                'customJs'         => $customJs,
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
