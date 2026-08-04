<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    public function edit()
    {
        $footerLinkRows = [];
        for ($i = 1; $i <= 3; $i++) {
            $footerLinkRows[] = [
                'label' => Setting::get("footer_link_{$i}_label", ''),
                'url'   => Setting::get("footer_link_{$i}_url", ''),
            ];
        }

        $locales = Locales::supported();

        $titleRows = [];
        $subtitleRows = [];
        foreach ($locales as $code => $info) {
            $titleRows[$code] = Setting::get("site_title_{$code}", '');
            $subtitleRows[$code] = Setting::get("site_subtitle_{$code}", '');
        }

        return view('admin.settings', [
            'logoUrl'          => Setting::publicUrl(Setting::get('site_logo_key')),
            'faviconUrl'       => Setting::publicUrl(Setting::get('site_favicon_key')),
            'defaultLocale'    => Setting::get('default_locale', config('app.locale')),
            'postsPerPage'     => Setting::get('posts_per_page', '12'),
            'postPathPrefix'   => Setting::get('post_path_prefix', 'p'),
            'footerHtml'       => Setting::get('footer_html', ''),
            'hideTitleSection' => (bool) Setting::get('hide_title_section'),
            'hideFilterLabel'  => (bool) Setting::get('hide_filter_label'),
            'titleRows'        => $titleRows,
            'subtitleRows'     => $subtitleRows,
            'locales'          => $locales,
            'footerLinkRows'   => $footerLinkRows,
        ]);
    }

    public function update(Request $request)
    {
        $supportedLocales = array_keys(Locales::supported());

        $validated = $request->validate([
            'logo'                  => ['nullable', 'file', 'mimes:jpeg,png,webp,gif,svg', 'max:2048'],
            'remove_logo'           => ['nullable', 'boolean'],
            'favicon'               => ['nullable', 'file', 'mimes:png,ico,svg,webp', 'max:512'],
            'remove_favicon'        => ['nullable', 'boolean'],
            'default_locale'        => ['required', 'string', 'in:'.implode(',', $supportedLocales)],
            'posts_per_page'        => ['required', 'integer', 'min:1', 'max:100'],
            'post_path_prefix'      => ['nullable', 'string', 'max:16', 'regex:/^[a-z0-9_-]+$/'],
            'hide_title_section'    => ['nullable', 'boolean'],
            'hide_filter_label'     => ['nullable', 'boolean'],
            'footer_html'           => ['nullable', 'string', 'max:10000'],
            'footer_links'          => ['nullable', 'array', 'max:3'],
            'footer_links.*.label'  => ['nullable', 'string', 'max:60'],
            'footer_links.*.url'    => ['nullable', 'url', 'max:1024'],
        ]);

        // Per-locale title & subtitle
        foreach ($supportedLocales as $locale) {
            $request->validate([
                "site_title_{$locale}"    => ['nullable', 'string', 'max:120'],
                "site_subtitle_{$locale}" => ['nullable', 'string', 'max:200'],
            ]);
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
        Setting::put('posts_per_page', (string) $validated['posts_per_page']);

        // Post path prefix
        $prefix = trim((string) ($validated['post_path_prefix'] ?? ''));
        if ($prefix !== '' && $prefix !== 'p') {
            Setting::put('post_path_prefix', $prefix);
        } else {
            Setting::forget('post_path_prefix');
        }

        // Hide title section
        if ($request->boolean('hide_title_section')) {
            Setting::put('hide_title_section', '1');
        } else {
            Setting::forget('hide_title_section');
        }

        // Hide filter label
        if ($request->boolean('hide_filter_label')) {
            Setting::put('hide_filter_label', '1');
        } else {
            Setting::forget('hide_filter_label');
        }

        // Footer HTML
        $footerHtml = trim((string) ($validated['footer_html'] ?? ''));
        if ($footerHtml !== '') {
            Setting::put('footer_html', $footerHtml);
        } else {
            Setting::forget('footer_html');
        }

        // Per-locale title & subtitle
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
        }

        $footerLinks = $validated['footer_links'] ?? [];
        for ($i = 1; $i <= 3; $i++) {
            $row   = $footerLinks[$i - 1] ?? [];
            $label = trim((string) ($row['label'] ?? ''));
            $url   = trim((string) ($row['url'] ?? ''));
            if ($label !== '' && $url !== '') {
                Setting::put("footer_link_{$i}_label", $label);
                Setting::put("footer_link_{$i}_url", $url);
            } else {
                Setting::forget("footer_link_{$i}_label");
                Setting::forget("footer_link_{$i}_url");
            }
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
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $key = $prefix.'/'.Str::uuid().'.'.$ext;
        Storage::disk('r2')->putFileAs('', $file, $key, [
            'visibility'  => 'public',
            'ContentType' => $file->getMimeType(),
        ]);
        return $key;
    }
}
