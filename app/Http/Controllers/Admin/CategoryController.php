<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('images')->orderBy('handle')->get();
        return view('admin.categories', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'handle'     => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/'],
            'name_en_US' => ['nullable', 'string', 'max:128'],
            'name_es_MX' => ['nullable', 'string', 'max:128'],
            'name_pt_BR' => ['nullable', 'string', 'max:128'],
        ]);

        $handle = Str::lower($validated['handle']);

        if (Category::where('handle', $handle)->exists()) {
            return redirect()->back()
                ->withErrors(['handle' => __('messages.category_handle_taken')])
                ->withInput();
        }

        if (empty($validated['name_en_US']) && empty($validated['name_es_MX']) && empty($validated['name_pt_BR'])) {
            return redirect()->back()
                ->withErrors(['name_en_US' => __('messages.category_at_least_one_name')])
                ->withInput();
        }

        Category::create([
            'handle'     => $handle,
            'name_en_US' => $validated['name_en_US'] ?? null,
            'name_es_MX' => $validated['name_es_MX'] ?? null,
            'name_pt_BR' => $validated['name_pt_BR'] ?? null,
        ]);

        return redirect()->route('admin.categories.index')
            ->with('status', __('messages.category_created'));
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'handle'     => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/'],
            'name_en_US' => ['nullable', 'string', 'max:128'],
            'name_es_MX' => ['nullable', 'string', 'max:128'],
            'name_pt_BR' => ['nullable', 'string', 'max:128'],
        ]);

        $handle = Str::lower($validated['handle']);

        if ($handle !== $category->handle && Category::where('handle', $handle)->exists()) {
            return redirect()->back()
                ->withErrors(['handle' => __('messages.category_handle_taken')])
                ->withInput();
        }

        if (empty($validated['name_en_US']) && empty($validated['name_es_MX']) && empty($validated['name_pt_BR'])) {
            return redirect()->back()
                ->withErrors(['name_en_US' => __('messages.category_at_least_one_name')])
                ->withInput();
        }

        $category->update([
            'handle'     => $handle,
            'name_en_US' => $validated['name_en_US'] ?? null,
            'name_es_MX' => $validated['name_es_MX'] ?? null,
            'name_pt_BR' => $validated['name_pt_BR'] ?? null,
        ]);

        return redirect()->route('admin.categories.index')
            ->with('status', __('messages.category_updated'));
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return redirect()->route('admin.categories.index')
            ->with('status', __('messages.category_deleted'));
    }
}
