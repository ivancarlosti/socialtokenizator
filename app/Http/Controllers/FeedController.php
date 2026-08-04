<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FeedController extends Controller
{
    public function index(Request $request): Response
    {
        $categoryHandle = trim((string) $request->query('category', ''));
        $tagFilter = trim((string) $request->query('tag', ''));
        $limit = max(1, min(100, (int) Setting::get('feed_posts_count', 10)));

        $query = Image::query()->with(['categories', 'tags']);

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

        $images = $query->latest()->take($limit)->get();

        $feedTitle = Setting::get('site_title_' . app()->getLocale()) ?: config('app.name');
        if ($categoryHandle !== '') {
            $feedTitle .= ' — ' . $categoryHandle;
        } elseif ($tagFilter !== '') {
            $feedTitle .= ' — #' . $tagFilter;
        }

        $xml = $this->buildAtomXml($images, $feedTitle, $categoryHandle, $tagFilter);

        return response($xml, 200)
            ->header('Content-Type', 'application/atom+xml; charset=utf-8');
    }

    private function buildAtomXml($images, string $feedTitle, string $categoryHandle, string $tagFilter): string
    {
        $feedId = route('feed');
        $feedUrl = route('feed');
        $queryParams = [];
        if ($categoryHandle !== '') {
            $queryParams['category'] = $categoryHandle;
        } elseif ($tagFilter !== '') {
            $queryParams['tag'] = $tagFilter;
        }
        if ($queryParams) {
            $feedId .= '?' . http_build_query($queryParams);
            $feedUrl .= '?' . http_build_query($queryParams);
        }

        $updated = $images->first()?->created_at?->toAtomString()
            ?? now()->toAtomString();

        $defaultLocale = Setting::get('default_locale', 'en');

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $feed = $dom->createElementNS('http://www.w3.org/2005/Atom', 'feed');
        $feed->setAttribute('xml:lang', str_replace('_', '-', $defaultLocale));
        $dom->appendChild($feed);

        $this->appendElement($dom, $feed, 'id', $feedId);
        $this->appendElement($dom, $feed, 'title', $feedTitle);
        $this->appendElement($dom, $feed, 'updated', $updated);
        $this->appendLink($dom, $feed, 'self', $feedUrl, 'application/atom+xml');
        $this->appendLink($dom, $feed, 'alternate', url('/'), 'text/html');

        $siteTitle = Setting::get('site_title_' . $defaultLocale) ?: config('app.name');
        $author = $dom->createElement('author');
        $this->appendElement($dom, $author, 'name', $siteTitle);
        $feed->appendChild($author);

        foreach ($images as $image) {
            $entry = $dom->createElement('entry');

            $entryId = route('image.show', ['uuid' => $image->uuid]);
            $this->appendElement($dom, $entry, 'id', $entryId);

            $headline = $image->getHeadline($defaultLocale);
            $description = $image->getDescription($defaultLocale);
            $title = $headline ?: ($description ? \Illuminate\Support\Str::limit($description, 80) : 'Untitled');
            $this->appendElement($dom, $entry, 'title', $title);

            $this->appendElement($dom, $entry, 'updated', $image->created_at->toAtomString());

            if ($description) {
                $htmlContent = '<div><img src="' . htmlspecialchars($image->public_url, ENT_XML1, 'UTF-8') . '" alt="" />';
                $htmlContent .= '<p>' . htmlspecialchars($description, ENT_XML1, 'UTF-8') . '</p></div>';
                $contentEl = $dom->createElement('content');
                $contentEl->setAttribute('type', 'html');
                $contentEl->appendChild($dom->createCDATASection($htmlContent));
                $entry->appendChild($contentEl);

                $summary = \Illuminate\Support\Str::limit($description, 300);
                $this->appendElement($dom, $entry, 'summary', $summary);
            }

            $this->appendLink($dom, $entry, 'alternate', $entryId, 'text/html');

            // Categories as Atom categories
            foreach ($image->categories as $cat) {
                $catEl = $dom->createElement('category');
                $catEl->setAttribute('term', $cat->handle);
                $catEl->setAttribute('label', $cat->getName($defaultLocale) ?? $cat->handle);
                $entry->appendChild($catEl);
            }

            $feed->appendChild($entry);
        }

        return $dom->saveXML();
    }

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
