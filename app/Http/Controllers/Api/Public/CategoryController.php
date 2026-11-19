<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::orderBy('name', 'ASC')->get();

        return response()->json([
            'success' => true,
            'message' => 'List Data Categories',
            'data'    => $categories,
        ], 200);
    }

    public function show($slug)
    {
        $category = Category::with('products.category', 'products.ratings')
            ->where('slug', $slug)
            ->first();

        if ($category) {
            return response()->json([
                'success' => true,
                'message' => 'Detail Data Category: ' . $category->name,
                'data'    => $category,
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Data Category Tidak Ditemukan!',
        ], 404);
    }
}
