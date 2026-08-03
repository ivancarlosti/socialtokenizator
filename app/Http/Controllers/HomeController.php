<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Models\Setting;
use App\Models\Tag;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $category = trim((string) $request->query('category', ''));
        $perPage = max(1, min(100, (int) Setting::get('posts_per_page', 12)));

        $query = Image::query()->with(['tags', 'sources']);

        if ($category !== '') {
            $query->whereHas('tags', function ($q) use ($category) {
                $q->where('name', Tag::normalize($category));
            });
        }

        $images = $query->latest()->paginate($perPage)->withQueryString();
        $categories = Tag::orderBy('name')->get();

        return view('home', compact('images', 'categories', 'category'));
    }

    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $perPage = max(1, min(100, (int) Setting::get('posts_per_page', 12)));

        $query = Image::query()->with('tags');

        if ($q !== '') {
            $tagSlug = Tag::normalize($q);
            $query->where(function ($w) use ($q, $tagSlug) {
                $w->where('description', 'like', '%'.$q.'%')
                  ->orWhereHas('tags', function ($t) use ($tagSlug) {
                      $t->where('name', $tagSlug);
                  });
            });
        }

        $images = $query->latest()->paginate($perPage)->withQueryString();

        return view('search', compact('images', 'q'));
    }
}
