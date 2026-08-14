<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Rules\ImageFile;
use App\Support\ImageMime;
use App\Support\IpWhitelist;
use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function edit()
    {
        $locales = Locales::supported();

        $titleRows = [];
        $subtitleRows = [];
        $footerTextRows = [];
        $footerHtmlRows = [];
        $aboutRows = [];
        foreach ($locales as $code => $info) {
            $titleRows[$code] = Setting::get("site_title_{$code}", '');
            $subtitleRows[$code] = Setting::get("site_subtitle_{$code}", '');
            $footerTextRows[$code] = Setting::get("footer_text_{$code}", '');
            $footerHtmlRows[$code] = Setting::get("footer_html_{$code}", '');
            $aboutRows[$code] = Setting::get("about_page_{$code}", '');
        }

        $apiToken = Setting::get('api_token');

        return view('admin.settings', [
            'logoUrl'          => Setting::publicUrl(Setting::get('site_logo_key')),
            'faviconUrl'       => Setting::publicUrl(Setting::get('site_favicon_key')),
            'defaultLocale'    => Setting::get('default_locale', config('app.locale')),
            'defaultTheme'     => Setting::get('default_theme', 'dark'),
            'postsPerPage'     => Setting::get('posts_per_page', '12'),
            'feedPostsCount'   => Setting::get('feed_posts_count', '10'),
            'postPathPrefix'   => Setting::get('post_path_prefix', 'p'),
            'hideTitleSection' => (bool) Setting::get('hide_title_section'),
            'hideFilterLabel'  => (bool) Setting::get('hide_filter_label'),
            'titleRows'          => $titleRows,
            'subtitleRows'       => $subtitleRows,
            'footerTextRows'     => $footerTextRows,
            'footerHtmlRows'     => $footerHtmlRows,
            'aboutRows'          => $aboutRows,
            'locales'            => $locales,
            'apiToken'           => $apiToken,
            'apiAllowedIps'      => Setting::get('api_allowed_ips', ''),
            'aiGeneratePrompt'   => Setting::get('ai_generate_prompt', ''),
            'robotsEnabled'      => (bool) Setting::get('robots_enabled', true),
            'robotsContent'      => Setting::get('robots_content', "User-agent: *\nDisallow: /admin\nDisallow: /auth"),
            'llmsEnabled'        => (bool) Setting::get('llms_enabled'),
            'llmsFullEnabled'    => (bool) Setting::get('llms_full_enabled'),
            'sitemapEnabled'     => (bool) Setting::get('sitemap_enabled'),
        ]);
    }

    public function update(Request $request)
    {
        $supportedLocales = array_keys(Locales::supported());

        $validated = $request->validate([
            'logo'                  => ['nullable', 'file', new ImageFile(['jpeg', 'png', 'webp', 'gif', 'svg', 'avif']), 'max:2048'],
            'remove_logo'           => ['nullable', 'boolean'],
            'favicon'               => ['nullable', 'file', new ImageFile(['png', 'ico', 'svg', 'webp']), 'max:512'],
            'remove_favicon'        => ['nullable', 'boolean'],
            'default_locale'        => ['required', 'string', 'in:'.implode(',', $supportedLocales)],
            'posts_per_page'        => ['required', 'integer', 'min:1', 'max:100'],
            'feed_posts_count'      => ['required', 'integer', 'min:1', 'max:100'],
            'post_path_prefix'      => ['nullable', 'string', 'max:16', 'regex:/^[a-z0-9_-]+$/'],
            'hide_title_section'    => ['nullable', 'boolean'],
            'hide_filter_label'     => ['nullable', 'boolean'],
            'default_theme'         => ['required', 'string', 'in:dark,light'],
            'ai_generate_prompt'    => ['nullable', 'string', 'max:20000'],
            'robots_enabled'        => ['nullable', 'boolean'],
            'robots_content'        => ['nullable', 'string', 'max:5000'],
            'llms_enabled'          => ['nullable', 'boolean'],
            'llms_full_enabled'     => ['nullable', 'boolean'],
            'sitemap_enabled'       => ['nullable', 'boolean'],
            'api_allowed_ips'       => ['nullable', 'string', 'max:10000'],
        ]);

        // Per-locale fields
        foreach ($supportedLocales as $locale) {
            $request->validate([
                "site_title_{$locale}"    => ['nullable', 'string', 'max:120'],
                "site_subtitle_{$locale}" => ['nullable', 'string', 'max:200'],
                "footer_text_{$locale}"   => ['nullable', 'string', 'max:10000'],
                "footer_html_{$locale}"   => ['nullable', 'string', 'max:10000'],
                "about_page_{$locale}"    => ['nullable', 'string', 'max:20000'],
            ]);
        }

        // API IP allowlist — validate each entry before any settings are persisted
        $allowedIps = trim((string) ($validated['api_allowed_ips'] ?? ''));

        foreach (IpWhitelist::normalize($allowedIps) as $entry) {
            if (! IpWhitelist::isValidEntry($entry)) {
                throw ValidationException::withMessages([
                    'api_allowed_ips' => [__('messages.settings_api_allowed_ips_invalid', ['ip' => $entry])],
                ]);
            }
        }

        if ($request->boolean('remove_logo')) {
            $this->deleteCurrent('site_logo_key');
        }
        if ($request->hasFile('logo')) {
            $this->deleteCurrent('site_logo_key');
            $key = $this->storeAsset($request->file('logo'), 'branding/logo');
            Setting::put('site_logo_key', $key);
        }

        if ($request->boolean('remove_favicon')) {
            $this->deleteCurrent('site_favicon_key');
        }
        if ($request->hasFile('favicon')) {
            $this->deleteCurrent('site_favicon_key');
            $key = $this->storeAsset($request->file('favicon'), 'branding/favicon');
            Setting::put('site_favicon_key', $key);
        }

        Setting::put('default_locale', $validated['default_locale']);
        Setting::put('default_theme', $validated['default_theme']);
        Setting::put('posts_per_page', (string) $validated['posts_per_page']);
        Setting::put('feed_posts_count', (string) $validated['feed_posts_count']);

        // Post path prefix
        $prefix = trim((string) ($validated['post_path_prefix'] ?? ''));
        if ($prefix !== '' && $prefix !== 'p') {
            Setting::put('post_path_prefix', $prefix);
        } else {
            Setting::forget('post_path_prefix');
        }

        // Hide title section
        if ($request->has('hide_title_section') && $request->input('hide_title_section') === '1') {
            Setting::put('hide_title_section', '1');
        } else {
            Setting::forget('hide_title_section');
        }

        // Hide filter label
        if ($request->has('hide_filter_label') && $request->input('hide_filter_label') === '1') {
            Setting::put('hide_filter_label', '1');
        } else {
            Setting::forget('hide_filter_label');
        }

        // Per-locale title, subtitle & footer HTML
        foreach ($supportedLocales as $locale) {
            $titleVal = trim((string) ($request->input("site_title_{$locale}", '')));
            $subVal   = trim((string) ($request->input("site_subtitle_{$locale}", '')));
            if ($titleVal !== '') {
                Setting::put("site_title_{$locale}", $titleVal);
            } else {
                Setting::forget("site_title_{$locale}");
            }
            if ($subVal !== '') {
                Setting::put("site_subtitle_{$locale}", $subVal);
            } else {
                Setting::forget("site_subtitle_{$locale}");
            }
            $footerTextVal = trim((string) ($request->input("footer_text_{$locale}", '')));
            if ($footerTextVal !== '') {
                Setting::put("footer_text_{$locale}", $footerTextVal);
            } else {
                Setting::forget("footer_text_{$locale}");
            }
            $footerVal = trim((string) ($request->input("footer_html_{$locale}", '')));
            if ($footerVal !== '') {
                Setting::put("footer_html_{$locale}", $footerVal);
            } else {
                Setting::forget("footer_html_{$locale}");
            }
            $aboutVal = trim((string) ($request->input("about_page_{$locale}", '')));
            if ($aboutVal !== '') {
                Setting::put("about_page_{$locale}", $aboutVal);
            } else {
                Setting::forget("about_page_{$locale}");
            }
        }

        // API token management
        $apiTokenAction = trim((string) ($request->input('api_token_action', '')));
        if ($apiTokenAction === 'generate' || $apiTokenAction === 'regenerate') {
            Setting::put('api_token', Str::random(64));
        }

        // API IP allowlist
        if ($allowedIps !== '') {
            Setting::put('api_allowed_ips', $allowedIps);
        } else {
            Setting::forget('api_allowed_ips');
        }

        // AI Generate Prompt
        $aiPrompt = trim((string) ($request->input('ai_generate_prompt', '')));
        if ($aiPrompt !== '') {
            Setting::put('ai_generate_prompt', $aiPrompt);
        } else {
            Setting::forget('ai_generate_prompt');
        }

        // Web Standards — robots.txt
        if ($request->has('robots_enabled') && $request->input('robots_enabled') === '1') {
            Setting::put('robots_enabled', '1');
        } else {
            Setting::forget('robots_enabled');
        }
        $robotsContent = trim((string) ($request->input('robots_content', '')));
        if ($robotsContent !== '') {
            Setting::put('robots_content', $robotsContent);
        } else {
            Setting::forget('robots_content');
        }

        // Web Standards — llms.txt
        if ($request->has('llms_enabled') && $request->input('llms_enabled') === '1') {
            Setting::put('llms_enabled', '1');
        } else {
            Setting::forget('llms_enabled');
        }

        // Web Standards — llms-full.txt
        if ($request->has('llms_full_enabled') && $request->input('llms_full_enabled') === '1') {
            Setting::put('llms_full_enabled', '1');
        } else {
            Setting::forget('llms_full_enabled');
        }

        // Web Standards — sitemap.xml
        if ($request->has('sitemap_enabled') && $request->input('sitemap_enabled') === '1') {
            Setting::put('sitemap_enabled', '1');
        } else {
            Setting::forget('sitemap_enabled');
        }

        // Invalidate web-standards caches so they regenerate on next request
        \Illuminate\Support\Facades\Cache::forget('web_standards.llms_txt');
        \Illuminate\Support\Facades\Cache::forget('web_standards.llms_full_txt');
        \Illuminate\Support\Facades\Cache::forget('web_standards.sitemap_xml');

        // Clear caches so settings take effect immediately.
        // Application cache (settings themselves):
        Setting::flushCache();
        // Route cache: post_path_prefix is read at route-registration time in routes/web.php
        try {
            Artisan::call('route:clear');
        } catch (\Throwable) {
            // Non-critical — routes will pick up the prefix on next container restart
        }

        return redirect()->route('admin.settings.edit')
            ->with('status', __('messages.settings_saved'));
    }

    private function deleteCurrent(string $settingKey): void
    {
        $existing = Setting::get($settingKey);
        if ($existing) {
            Storage::disk('r2')->delete($existing);
            Setting::forget($settingKey);
        }
    }

    private function storeAsset(\Illuminate\Http\UploadedFile $file, string $prefix): string
    {
        $mime = ImageMime::ofUploadedFile($file) ?? $file->getMimeType();
        $ext = ImageMime::extension($mime);
        $key = $prefix.'/'.Str::uuid().'.'.$ext;
        Storage::disk('r2')->putFileAs('', $file, $key, [
            'visibility'  => 'public',
            'ContentType' => $mime,
        ]);
        return $key;
    }
}
