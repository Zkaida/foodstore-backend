<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Rating;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Validator;

class RatingController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth:api'),
        ];
    }

    public function __invoke(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id'            => 'required|exists:products,id',
            'transaction_detail_id' => 'nullable|exists:transaction_details,id',
            'rating'                => 'required|integer|min:1|max:5',
            'review'                => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $check_rating = Rating::where('product_id', $request->product_id)
            ->where('customer_id', auth()->guard('api')->user()->id)
            ->when($request->transaction_detail_id, function ($query) use ($request) {
                return $query->where('transaction_detail_id', $request->transaction_detail_id);
            })
            ->first();

        if ($check_rating) {
            return response()->json([
                'success' => false,
                'message' => 'Anda Sudah Memberikan Rating Untuk Produk Ini!',
            ], 409);
        }

        $rating = Rating::create([
            'customer_id'           => auth()->guard('api')->user()->id,
            'product_id'            => $request->product_id,
            'transaction_detail_id' => $request->transaction_detail_id,
            'rating'                => $request->rating,
            'review'                => $request->review,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rating Berhasil Disimpan!',
            'data'    => $rating,
        ], 201);
    }
}
