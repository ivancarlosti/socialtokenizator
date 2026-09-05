<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Models\Setting;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FeedController extends Controller
{
    /**
     * Resolve the feed locale from the request or fall back to defaults.
     */
    private function resolveFeedLocale(Request $request): string
    {
        $lang = trim((string) $request->query('lang', ''));

        if ($lang !== '' && Locales::isSupported($lang)) {
            return $lang;
        }

        try {
            $default = (string) Setting::get('default_locale', config('app.locale', 'pt_BR'));
        } catch (\Throwable) {
            $default = (string) config('app.locale', 'pt_BR');
        }

        return Locales::isSupported($default) ? $default : 'pt_BR';
    }

    /**
     * Build the base query for feeds: locale-filtered, category/tag-filtered, limited.
     */
    private function buildFeedQuery(Request $request, string $locale)
    {
        $categoryHandle = trim((string) $request->query('category', ''));
        $tagFilter = trim((string) $request->query('tag', ''));
        $limit = max(1, min(100, (int) Setting::get('feed_posts_count', 10)));

        // Build locale column suffixes (e.g. en_US, pt_BR)
        $localeSuffix = str_replace('-', '_', $locale);

        $query = Image::query()->with(['categories', 'tags', 'author']);

        // Only include images that have content in the requested locale
        $headlineCol = 'headline_' . $localeSuffix;
        $descCol = 'description_' . $localeSuffix;
        $query->where(function ($q) use ($headlineCol, $descCol) {
            $q->whereNotNull($headlineCol)->where($headlineCol, '!=', '')
              ->orWhereNotNull($descCol)->where($descCol, '!=', '');
        });

        if ($categoryHandle !== '') {
            $query->whereHas('categories', function ($q) use ($categoryHandle) {
                $q->where('handle', $categoryHandle);
            });
        }

        if ($tagFilter !== '') {
            $query->whereHas('tags', function ($q) use ($tagFilter) {
                $q->where('name', $tagFilter);
            });
        }

        return $query->latest()->take($limit)->get();
    }

    /**
     * Build a human-readable feed title.
     */
    private function buildFeedTitle(string $locale, string $categoryHandle, string $tagFilter): string
    {
        $title = Setting::get('site_title_' . $locale) ?: config('app.name');

        if ($categoryHandle !== '') {
            $title .= ' — ' . $categoryHandle;
        } elseif ($tagFilter !== '') {
            $title .= ' — #' . $tagFilter;
        }

        return $title;
    }

    /**
     * Resolve the feed post description settings (enable/mode/length).
     */
    private function feedDescriptionSettings(): array
    {
        $enabled = (bool) Setting::get('show_post_description_in_feed', true);
        $mode = Setting::get('post_description_in_feed_mode', 'full');
        $length = (int) Setting::get('post_description_in_feed_length', '300');

        if (! in_array($mode, ['excerpt', 'full'], true)) {
            $mode = 'full';
        }
        if ($length < 1 || $length > 2000) {
            $length = 300;
        }

        return [$enabled, $mode, $length];
    }

    private function feedDescriptionText(string $description, string $mode, int $length): string
    {
        return $mode === 'excerpt' ? \Illuminate\Support\Str::limit($description, $length) : $description;
    }

    // ──────────────────────────────────────────────
    //  Atom feed
    // ──────────────────────────────────────────────

    public function atom(Request $request): Response
    {
        $locale = $this->resolveFeedLocale($request);
        $categoryHandle = trim((string) $request->query('category', ''));
        $tagFilter = trim((string) $request->query('tag', ''));

        $images = $this->buildFeedQuery($request, $locale);
        $feedTitle = $this->buildFeedTitle($locale, $categoryHandle, $tagFilter);

        $xml = $this->buildAtomXml($images, $feedTitle, $locale, $categoryHandle, $tagFilter);

        return response($xml, 200)
            ->header('Content-Type', 'application/atom+xml; charset=utf-8');
    }

    private function buildAtomXml($images, string $feedTitle, string $locale, string $categoryHandle, string $tagFilter): string
    {
        [$showDescription, $descMode, $descLength] = $this->feedDescriptionSettings();
        $queryParams = ['lang' => $locale];
        if ($categoryHandle !== '') {
            $queryParams['category'] = $categoryHandle;
        } elseif ($tagFilter !== '') {
            $queryParams['tag'] = $tagFilter;
        }
        $feedUrl = route('feed.atom', $queryParams);
        $feedId = $feedUrl;

        $updated = $images->first()?->created_at?->toAtomString()
            ?? now()->toAtomString();

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $feed = $dom->createElementNS('http://www.w3.org/2005/Atom', 'feed');
        $feed->setAttribute('xml:lang', str_replace('_', '-', $locale));
        $dom->appendChild($feed);

        $this->appendElement($dom, $feed, 'id', $feedId);
        $this->appendElement($dom, $feed, 'title', $feedTitle);
        $this->appendElement($dom, $feed, 'updated', $updated);
        $this->appendLink($dom, $feed, 'self', $feedUrl, 'application/atom+xml');
        $this->appendLink($dom, $feed, 'alternate', url('/'), 'text/html');

        $siteTitle = Setting::get('site_title_' . $locale) ?: config('app.name');
        $author = $dom->createElement('author');
        $this->appendElement($dom, $author, 'name', $siteTitle);
        $feed->appendChild($author);

        foreach ($images as $image) {
            $entry = $dom->createElement('entry');

            $entryId = route('image.show', ['slug' => $image->short_id]);
            $this->appendElement($dom, $entry, 'id', $entryId);

            $headline = $image->getHeadline($locale);
            $description = $image->getDescription($locale);
            $title = $headline ?: ($description ? \Illuminate\Support\Str::limit($description, 80) : 'Untitled');
            $this->appendElement($dom, $entry, 'title', $title);

            if ($image->author) {
                $authorEl = $dom->createElement('author');
                $this->appendElement($dom, $authorEl, 'name', $image->author->displayName());
                $this->appendElement($dom, $authorEl, 'email', $image->author->email);
                $entry->appendChild($authorEl);
            }

            $this->appendElement($dom, $entry, 'updated', ($image->updated_at ?? $image->created_at)->toAtomString());

            if ($showDescription && $description) {
                $descText = $this->feedDescriptionText($description, $descMode, $descLength);
                $htmlContent = '<div><img src="' . htmlspecialchars($image->og_image_url, ENT_XML1, 'UTF-8') . '" alt="" />';
                $htmlContent .= '<p>' . htmlspecialchars($descText, ENT_XML1, 'UTF-8') . '</p></div>';
                $contentEl = $dom->createElement('content');
                $contentEl->setAttribute('type', 'html');
                $contentEl->appendChild($dom->createCDATASection($htmlContent));
                $entry->appendChild($contentEl);

                $summary = $descMode === 'excerpt' ? $descText : \Illuminate\Support\Str::limit($description, 300);
                $this->appendElement($dom, $entry, 'summary', $summary);
            }

            $this->appendLink($dom, $entry, 'alternate', $entryId, 'text/html');

            foreach ($image->categories as $cat) {
                $catEl = $dom->createElement('category');
                $catEl->setAttribute('term', $cat->handle);
                $catEl->setAttribute('label', $cat->getName($locale) ?? $cat->handle);
                $entry->appendChild($catEl);
            }

            $feed->appendChild($entry);
        }

        return $dom->saveXML();
    }

    // ──────────────────────────────────────────────
    //  RSS 2.0 feed
    // ──────────────────────────────────────────────

    public function rss(Request $request): Response
    {
        $locale = $this->resolveFeedLocale($request);
        $categoryHandle = trim((string) $request->query('category', ''));
        $tagFilter = trim((string) $request->query('tag', ''));

        $images = $this->buildFeedQuery($request, $locale);
        $feedTitle = $this->buildFeedTitle($locale, $categoryHandle, $tagFilter);

        $xml = $this->buildRssXml($images, $feedTitle, $locale, $categoryHandle, $tagFilter);

        return response($xml, 200)
            ->header('Content-Type', 'application/rss+xml; charset=utf-8');
    }

    private function buildRssXml($images, string $feedTitle, string $locale, string $categoryHandle, string $tagFilter): string
    {
        [$showDescription, $descMode, $descLength] = $this->feedDescriptionSettings();
        $queryParams = ['lang' => $locale];
        if ($categoryHandle !== '') {
            $queryParams['category'] = $categoryHandle;
        } elseif ($tagFilter !== '') {
            $queryParams['tag'] = $tagFilter;
        }
        $feedUrl = route('feed.rss', $queryParams);
        $siteUrl = url('/');
        $siteTitle = Setting::get('site_title_' . $locale) ?: config('app.name');
        $lastBuildDate = $images->first()?->created_at?->toRssString()
            ?? now()->toRssString();
        $langTag = str_replace('_', '-', $locale);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $rss = $dom->createElement('rss');
        $rss->setAttribute('version', '2.0');
        $rss->setAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $dom->appendChild($rss);

        $channel = $dom->createElement('channel');
        $rss->appendChild($channel);

        $this->appendElement($dom, $channel, 'title', $feedTitle);
        $this->appendElement($dom, $channel, 'link', $siteUrl);
        $this->appendElement($dom, $channel, 'description', $feedTitle);
        $this->appendElement($dom, $channel, 'language', $langTag);
        $this->appendElement($dom, $channel, 'lastBuildDate', $lastBuildDate);

        // Atom self-link for interoperability
        $atomLink = $dom->createElementNS('http://www.w3.org/2005/Atom', 'atom:link');
        $atomLink->setAttribute('href', $feedUrl);
        $atomLink->setAttribute('rel', 'self');
        $atomLink->setAttribute('type', 'application/rss+xml');
        $channel->appendChild($atomLink);

        foreach ($images as $image) {
            $item = $dom->createElement('item');

            $entryUrl = route('image.show', ['slug' => $image->short_id]);
            $headline = $image->getHeadline($locale);
            $description = $image->getDescription($locale);
            $title = $headline ?: ($description ? \Illuminate\Support\Str::limit($description, 80) : 'Untitled');

            $this->appendElement($dom, $item, 'title', $title);
            $this->appendElement($dom, $item, 'link', $entryUrl);
            $this->appendElement($dom, $item, 'guid', $entryUrl);

            $pubDate = $image->created_at->toRssString();
            $this->appendElement($dom, $item, 'pubDate', $pubDate);

            if ($image->author) {
                $this->appendElement($dom, $item, 'author', $image->author->email);
            }

            if ($showDescription && $description) {
                $descText = $this->feedDescriptionText($description, $descMode, $descLength);
                $htmlContent = '<div><img src="' . htmlspecialchars($image->og_image_url, ENT_XML1, 'UTF-8') . '" alt="" />';
                $htmlContent .= '<p>' . htmlspecialchars($descText, ENT_XML1, 'UTF-8') . '</p></div>';
                $descEl = $dom->createElement('description');
                $descEl->appendChild($dom->createCDATASection($htmlContent));
                $item->appendChild($descEl);
            }

            // Categories as RSS category elements
            foreach ($image->categories as $cat) {
                $catEl = $dom->createElement('category', htmlspecialchars($cat->getName($locale) ?? $cat->handle, ENT_XML1, 'UTF-8'));
                $item->appendChild($catEl);
            }

            $channel->appendChild($item);
        }

        return $dom->saveXML();
    }

    // ──────────────────────────────────────────────
    //  JSON Feed v1.1
    // ──────────────────────────────────────────────

    public function json(Request $request): JsonResponse
    {
        $locale = $this->resolveFeedLocale($request);
        $categoryHandle = trim((string) $request->query('category', ''));
        $tagFilter = trim((string) $request->query('tag', ''));

        $images = $this->buildFeedQuery($request, $locale);
        $feedTitle = $this->buildFeedTitle($locale, $categoryHandle, $tagFilter);

        $data = $this->buildJsonFeed($images, $feedTitle, $locale, $categoryHandle, $tagFilter);

        return response()->json($data, 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Content-Type', 'application/feed+json; charset=utf-8');
    }

    private function buildJsonFeed($images, string $feedTitle, string $locale, string $categoryHandle, string $tagFilter): array
    {
        [$showDescription, $descMode, $descLength] = $this->feedDescriptionSettings();
        $queryParams = ['lang' => $locale];
        if ($categoryHandle !== '') {
            $queryParams['category'] = $categoryHandle;
        } elseif ($tagFilter !== '') {
            $queryParams['tag'] = $tagFilter;
        }
        $feedUrl = route('feed.json', $queryParams);
        $siteUrl = url('/');
        $langTag = str_replace('_', '-', $locale);

        $items = [];
        foreach ($images as $image) {
            $entryUrl = route('image.show', ['slug' => $image->short_id]);
            $headline = $image->getHeadline($locale);
            $description = $image->getDescription($locale);
            $title = $headline ?: ($description ? \Illuminate\Support\Str::limit($description, 80) : 'Untitled');

            $item = [
                'id' => $entryUrl,
                'url' => $entryUrl,
                'title' => $title,
                'image' => $image->og_image_url,
                'date_published' => $image->created_at->toIso8601String(),
                'author' => $image->author ? ['name' => $image->author->displayName()] : null,
            ];

            if ($image->updated_at && $image->updated_at->greaterThan($image->created_at)) {
                $item['date_modified'] = $image->updated_at->toIso8601String();
            }

            if ($showDescription && $description) {
                $descText = $this->feedDescriptionText($description, $descMode, $descLength);
                $item['content_html'] = '<div><img src="' . $image->og_image_url . '" alt="" /><p>' . htmlspecialchars($descText, ENT_QUOTES, 'UTF-8') . '</p></div>';
                $item['summary'] = $descMode === 'excerpt' ? $descText : \Illuminate\Support\Str::limit($description, 300);
            }

            $itemTags = [];
            foreach ($image->categories as $cat) {
                $itemTags[] = $cat->getName($locale) ?? $cat->handle;
            }
            if ($itemTags) {
                $item['tags'] = $itemTags;
            }

            $items[] = $item;
        }

        return [
            'version' => 'https://jsonfeed.org/version/1.1',
            'title' => $feedTitle,
            'home_page_url' => $siteUrl,
            'feed_url' => $feedUrl,
            'language' => $langTag,
            'items' => $items,
        ];
    }

    // ──────────────────────────────────────────────
    //  DOM helpers
    // ──────────────────────────────────────────────

    private function appendElement(\DOMDocument $dom, \DOMElement $parent, string $name, string $value): void
    {
        $el = $dom->createElement($name, $value);
        $parent->appendChild($el);
    }

    private function appendLink(\DOMDocument $dom, \DOMElement $parent, string $rel, string $href, string $type): void
    {
        $link = $dom->createElement('link');
        $link->setAttribute('rel', $rel);
        $link->setAttribute('href', $href);
        $link->setAttribute('type', $type);
        $parent->appendChild($link);
    }
}
