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
        return view('admin.settings', [
            'logoUrl'        => Setting::publicUrl(Setting::get('site_logo_key')),
            'faviconUrl'     => Setting::publicUrl(Setting::get('site_favicon_key')),
            'defaultLocale'  => Setting::get('default_locale', config('app.locale')),
            'locales'        => Locales::supported(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'logo'           => ['nullable', 'file', 'mimes:jpeg,png,webp,gif,svg', 'max:2048'],
            'remove_logo'    => ['nullable', 'boolean'],
            'favicon'        => ['nullable', 'file', 'mimes:png,ico,svg,webp', 'max:512'],
            'remove_favicon' => ['nullable', 'boolean'],
            'default_locale' => ['required', 'string', 'in:'.implode(',', array_keys(Locales::supported()))],
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
