<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Models\Setting;
use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WebStandardsController extends Controller
{
    /**
     * Serve robots.txt dynamically.
     */
    public function robots(): Response
    {
        if (! (bool) Setting::get('robots_enabled', true)) {
            abort(404);
        }

        $content = Setting::get('robots_content', "User-agent: *\nDisallow: /admin\nDisallow: /auth");

        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }

    /**
     * Serve llms.txt — machine-readable list of all posts with descriptions.
     * Includes content from ALL supported locales.
     */
    public function llms(): Response
    {
        if (! (bool) Setting::get('llms_enabled')) {
            abort(404);
        }

        $content = Cache::remember('web_standards.llms_txt', 3600, function () {
            $images = Image::query()->latest()->get();
            $locales = Locales::supported();
            $lines = [];

            $lines[] = '# ' . (Setting::get('site_title_' . config('app.locale')) ?: config('app.name'));
            $lines[] = '';

            foreach ($images as $image) {
                $url = route('image.show', ['slug' => $image->short_id]);

                foreach ($locales as $code => $info) {
                    $headline = $image->getHeadline($code);
                    $description = $image->getDescription($code);

                    if (! $headline && ! $description) {
                        continue;
                    }

                    $title = $headline ?: Str::limit($description, 80);
                    $desc = $description ? ': ' . Str::limit(strip_tags($description), 200) : '';

                    $lines[] = sprintf('- [%s](%s) [%s]%s', $title, $url, $code, $desc);
                }
            }

            return implode("\n", $lines);
        });

        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }

    /**
     * Serve llms-full.txt — full markdown content of all posts.
     * Includes content from ALL supported locales.
     */
    public function llmsFull(): Response
    {
        if (! (bool) Setting::get('llms_full_enabled')) {
            abort(404);
        }

        $content = Cache::remember('web_standards.llms_full_txt', 3600, function () {
            $images = Image::query()->with(['categories', 'tags'])->latest()->get();
            $locales = Locales::supported();
            $lines = [];

            $lines[] = '# ' . (Setting::get('site_title_' . config('app.locale')) ?: config('app.name')) . ' — Full Content';
            $lines[] = '';

            foreach ($images as $image) {
                $url = route('image.show', ['slug' => $image->short_id]);

                foreach ($locales as $code => $info) {
                    $headline = $image->getHeadline($code);
                    $description = $image->getDescription($code);

                    if (! $headline && ! $description) {
                        continue;
                    }

                    $title = $headline ?: Str::limit($description, 80);

                    $lines[] = '## ' . $title . ' [' . $code . ']';
                    $lines[] = '';
                    $lines[] = '**URL:** ' . $url;
                    $lines[] = '';

                    if ($description) {
                        $lines[] = strip_tags($description);
                        $lines[] = '';
                    }

                    // Categories
                    if ($image->categories->isNotEmpty()) {
                        $catNames = $image->categories->map(fn($c) => $c->getName($code) ?? $c->handle)->filter()->implode(', ');
                        if ($catNames) {
                            $lines[] = '**Categories:** ' . $catNames;
                            $lines[] = '';
                        }
                    }

                    // Tags
                    if ($image->tags->isNotEmpty()) {
                        $tagNames = $image->tags->pluck('name')->map(fn($n) => '#' . $n)->implode(', ');
                        $lines[] = '**Tags:** ' . $tagNames;
                        $lines[] = '';
                    }
                }

                $lines[] = '---';
                $lines[] = '';
            }

            return implode("\n", $lines);
        });

        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }

    /**
     * Serve sitemap.xml dynamically.
     */
    public function sitemap(): Response
    {
        if (! (bool) Setting::get('sitemap_enabled')) {
            abort(404);
        }

        $xml = Cache::remember('web_standards.sitemap_xml', 3600, function () {
            $images = Image::query()->latest()->get();

            $dom = new \DOMDocument('1.0', 'UTF-8');
            $dom->formatOutput = true;

            $urlset = $dom->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9', 'urlset');
            $dom->appendChild($urlset);

            // Homepage
            $this->appendSitemapUrl($dom, $urlset, url('/'), null, '1.0');

            // About page
            $this->appendSitemapUrl($dom, $urlset, route('about'), null, '0.8');

            // Posts
            foreach ($images as $image) {
                $url = route('image.show', ['slug' => $image->short_id]);
                $lastmod = $image->updated_at->toW3cString();
                $this->appendSitemapUrl($dom, $urlset, $url, $lastmod, '0.7');
            }

            return $dom->saveXML();
        });

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=utf-8');
    }

    /**
     * Serve the web app manifest (site.webmanifest) for PWA/Android support.
     */
    public function manifest(): Response
    {
        $locale = Locales::default();
        $siteTitle = Setting::get('site_title_' . $locale) ?: config('app.name');

        $icons = [];
        foreach ([
            '192' => 'site_favicon_192_key',
            '512' => 'site_favicon_512_key',
        ] as $size => $settingKey) {
            $iconUrl = Setting::publicUrl(Setting::get($settingKey));
            if ($iconUrl) {
                $icons[] = [
                    'src'   => $iconUrl,
                    'sizes' => $size . 'x' . $size,
                    'type'  => 'image/png',
                ];
            }
        }

        $manifest = [
            'name'             => $siteTitle,
            'short_name'       => Str::limit($siteTitle, 12, ''),
            'description'      => Setting::get('site_subtitle_' . $locale),
            'start_url'        => url('/'),
            'scope'            => url('/'),
            'display'          => 'standalone',
            'background_color' => Setting::get('theme_color_dark', '#111827'),
            'theme_color'      => Setting::get('theme_color_dark', '#111827'),
            'icons'            => $icons,
        ];

        $json = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return response($json, 200)
            ->header('Content-Type', 'application/manifest+json; charset=utf-8');
    }

    /**
     * Append a <url> element to the sitemap <urlset>.
     */
    private function appendSitemapUrl(\DOMDocument $dom, \DOMElement $urlset, string $loc, ?string $lastmod, string $priority): void
    {
        $urlEl = $dom->createElement('url');

        $locEl = $dom->createElement('loc', htmlspecialchars($loc, ENT_XML1, 'UTF-8'));
        $urlEl->appendChild($locEl);

        if ($lastmod) {
            $lastmodEl = $dom->createElement('lastmod', $lastmod);
            $urlEl->appendChild($lastmodEl);
        }

        $prioEl = $dom->createElement('priority', $priority);
        $urlEl->appendChild($prioEl);

        $urlset->appendChild($urlEl);
    }
}
