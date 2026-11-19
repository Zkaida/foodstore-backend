<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth:api'),
        ];
    }

    public function index()
    {
        $carts = Cart::with('product')
            ->where('customer_id', auth()->guard('api')->user()->id)
            ->latest()
            ->get();

        $totalWeight = 0;
        $totalPrice = 0;

        foreach ($carts as $cart) {
            if ($cart->product) {
                $totalWeight += $cart->product->weight * $cart->qty;
                $totalPrice += $cart->product->price * $cart->qty;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'List Data Carts: ' . auth()->guard('api')->user()->name,
            'data'    => [
                'carts'        => $carts,
                'total_weight' => $totalWeight,
                'total_price'  => $totalPrice,
            ],
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'qty'        => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $qty = $request->qty ?? 1;

        $item = Cart::where('product_id', $request->product_id)
            ->where('customer_id', auth()->guard('api')->user()->id)
            ->first();

        if ($item) {
            $item->increment('qty', $qty);
            $item = $item->fresh();
        } else {
            $item = Cart::create([
                'product_id'  => $request->product_id,
                'customer_id' => auth()->guard('api')->user()->id,
                'qty'         => $qty,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil Ditambahkan Ke Keranjang!',
            'data'    => $item,
        ], 201);
    }

    public function incrementCart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cart_id' => 'required|exists:carts,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $item = Cart::where('id', $request->cart_id)
            ->where('customer_id', auth()->guard('api')->user()->id)
            ->first();

        if ($item) {
            $item->increment('qty');

            return response()->json([
                'success' => true,
                'message' => 'Qty Cart Berhasil Ditambahkan!',
                'data'    => $item->fresh(),
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Data Cart Tidak Ditemukan!',
        ], 404);
    }

    public function decrementCart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cart_id' => 'required|exists:carts,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $item = Cart::where('id', $request->cart_id)
            ->where('customer_id', auth()->guard('api')->user()->id)
            ->first();

        if ($item) {
            if ($item->qty > 1) {
                $item->decrement('qty');

                return response()->json([
                    'success' => true,
                    'message' => 'Qty Cart Berhasil Dikurangi!',
                    'data'    => $item->fresh(),
                ], 200);
            }

            $item->delete();

            return response()->json([
                'success' => true,
                'message' => 'Item Cart Dihapus Karena Qty 0!',
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Data Cart Tidak Ditemukan!',
        ], 404);
    }

    public function destroy($id)
    {
        $cart = Cart::where('id', $id)
            ->where('customer_id', auth()->guard('api')->user()->id)
            ->first();

        if ($cart) {
            $cart->delete();

            return response()->json([
                'success' => true,
                'message' => 'Data Cart Berhasil Dihapus!',
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Data Cart Tidak Ditemukan!',
        ], 404);
    }
}
