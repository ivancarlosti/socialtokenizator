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
        $footerLinks = [];
        for ($i = 1; $i <= 3; $i++) {
            $footerLinks[] = [
                'label' => Setting::get("footer_link_{$i}_label", ''),
                'url'   => Setting::get("footer_link_{$i}_url", ''),
            ];
        }

        return view('admin.settings', [
            'logoUrl'        => Setting::publicUrl(Setting::get('site_logo_key')),
            'faviconUrl'     => Setting::publicUrl(Setting::get('site_favicon_key')),
            'defaultLocale'  => Setting::get('default_locale', config('app.locale')),
            'locales'        => Locales::supported(),
            'footerLinks'    => $footerLinks,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'logo'                  => ['nullable', 'file', 'mimes:jpeg,png,webp,gif,svg', 'max:2048'],
            'remove_logo'           => ['nullable', 'boolean'],
            'favicon'               => ['nullable', 'file', 'mimes:png,ico,svg,webp', 'max:512'],
            'remove_favicon'        => ['nullable', 'boolean'],
            'default_locale'        => ['required', 'string', 'in:'.implode(',', array_keys(Locales::supported()))],
            'footer_links'          => ['nullable', 'array', 'max:3'],
            'footer_links.*.label'  => ['nullable', 'string', 'max:60'],
            'footer_links.*.url'    => ['nullable', 'url', 'max:1024'],
        ]);

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
