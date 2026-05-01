<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Models\Tag;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        $image = Image::inRandomOrder()->with(['tags', 'sources'])->first();
        return view('home', compact('image'));
    }

    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

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

        $images = $query->latest()->paginate(24)->withQueryString();

        return view('search', compact('images', 'q'));
    }
}
