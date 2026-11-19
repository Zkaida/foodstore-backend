<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Product;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('category', 'ratings')
            ->withAvg('ratings', 'rating')
            ->when(request()->search, function ($products) {
                $products = $products->where('title', 'like', '%' . request()->search . '%');
            })
            ->latest()
            ->paginate(10);

        $products->getCollection()->transform(function ($product) {
            $product->ratings_avg_rating = number_format((float) $product->ratings_avg_rating, 1);
            return $product;
        });

        return response()->json([
            'success' => true,
            'message' => 'List Data Products',
            'data'    => $products,
        ], 200);
    }

    public function productPopular()
    {
        $products = Product::with('category', 'ratings')
            ->withAvg('ratings', 'rating')
            ->withCount(['ratings' => function ($query) {
                $query->where('rating', '>=', 4);
            }])
            ->orderByDesc('ratings_count')
            ->limit(5)
            ->get();

        $products->transform(function ($product) {
            $product->ratings_avg_rating = number_format((float) $product->ratings_avg_rating, 1);
            return $product;
        });

        return response()->json([
            'success' => true,
            'message' => 'List Data Products Popular',
            'data'    => $products,
        ], 200);
    }

    public function show($slug)
    {
        $product = Product::with('category', 'ratings.customer')
            ->withCount('ratings')
            ->withAvg('ratings', 'rating')
            ->where('slug', $slug)
            ->first();

        if ($product) {
            $product->ratings_avg_rating = number_format((float) $product->ratings_avg_rating, 1);

            return response()->json([
                'success' => true,
                'message' => 'Detail Data Product: ' . $product->title,
                'data'    => $product,
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Detail Data Product Tidak Ditemukan!',
        ], 404);
    }
}
