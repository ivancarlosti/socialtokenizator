<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /**
     * List all categories.
     */
    public function index(): JsonResponse
    {
        $categories = Category::orderBy('handle')->get();

        return response()->json([
            'data' => $categories->map(fn (Category $c) => [
                'id'         => $c->id,
                'handle'     => $c->handle,
                'name_en_US' => $c->name_en_US,
                'name_es_MX' => $c->name_es_MX,
                'name_pt_BR' => $c->name_pt_BR,
            ])->values(),
        ]);
    }
}
