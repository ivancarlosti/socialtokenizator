<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Tag::withCount('images')->orderBy('name')->get();
        return view('admin.categories', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64'],
        ]);

        $name = Tag::normalize($validated['name']);

        if (Tag::where('name', $name)->exists()) {
            return redirect()->back()
                ->withErrors(['name' => __('messages.category_name_taken')])
                ->withInput();
        }

        Tag::create(['name' => $name]);

        return redirect()->route('admin.categories.index')
            ->with('status', __('messages.category_created'));
    }

    public function update(Request $request, Tag $tag)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64'],
        ]);

        $name = Tag::normalize($validated['name']);

        if ($name === $tag->name) {
            return redirect()->route('admin.categories.index');
        }

        if (Tag::where('name', $name)->where('id', '!=', $tag->id)->exists()) {
            return redirect()->back()
                ->withErrors(['name' => __('messages.category_name_taken')])
                ->withInput();
        }

        $tag->update(['name' => $name]);

        return redirect()->route('admin.categories.index')
            ->with('status', __('messages.category_updated'));
    }

    public function destroy(Tag $tag)
    {
        $tag->delete();

        return redirect()->route('admin.categories.index')
            ->with('status', __('messages.category_deleted'));
    }
}
