<?php

namespace App\Support;

use App\Models\Setting;

class Locales
{
    public const SESSION_KEY = 'locale';

    /**
     * locale code => [name, flag ISO-3166 country code for flagcdn.com]
     */
    public static function supported(): array
    {
        return [
            'pt_BR' => ['name' => 'Português (Brasil)', 'flag' => 'br'],
            'en-US' => ['name' => 'English (US)',     'flag' => 'us'],
            'es_MX' => ['name' => 'Español (México)', 'flag' => 'mx'],
        ];
    }

    public static function isSupported(string $locale): bool
    {
        return array_key_exists($locale, self::supported());
    }

    public static function default(): string
    {
        try {
            $configured = (string) Setting::get('default_locale', config('app.locale', 'en'));
        } catch (\Throwable) {
            $configured = (string) config('app.locale', 'en');
        }
        return self::isSupported($configured) ? $configured : 'en';
    }

    public static function flagUrl(string $locale): ?string
    {
        $supported = self::supported();
        if (! isset($supported[$locale])) return null;
        return 'https://flagcdn.com/w40/'.$supported[$locale]['flag'].'.png';
    }
}
