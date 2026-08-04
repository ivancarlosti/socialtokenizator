<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Image;
use App\Models\Setting;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $categoryHandle = trim((string) $request->query('category', ''));
        $tagFilter = trim((string) $request->query('tag', ''));
        $perPage = max(1, min(100, (int) Setting::get('posts_per_page', 12)));

        $query = Image::query()->with(['categories', 'tags', 'sources']);

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

        $images = $query->latest()->paginate($perPage)->withQueryString();
        $categories = Category::orderBy('handle')->get();

        return view('home', compact('images', 'categories', 'categoryHandle', 'tagFilter'));
    }

    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $perPage = max(1, min(100, (int) Setting::get('posts_per_page', 12)));

        $query = Image::query()->with(['categories', 'tags']);

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('description_en', 'like', '%'.$q.'%')
                  ->orWhere('description_es', 'like', '%'.$q.'%')
                  ->orWhere('description_pt_BR', 'like', '%'.$q.'%')
                  ->orWhere('headline_en', 'like', '%'.$q.'%')
                  ->orWhere('headline_es', 'like', '%'.$q.'%')
                  ->orWhere('headline_pt_BR', 'like', '%'.$q.'%')
                  ->orWhereHas('tags', function ($t) use ($q) {
                      $t->where('name', \App\Models\Tag::normalize($q));
                  });
            });
        }

        $images = $query->latest()->paginate($perPage)->withQueryString();

        return view('search', compact('images', 'q'));
    }
}
