<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class MyOrderController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth:api'),
        ];
    }

    public function index()
    {
        $transactions = Transaction::with('shipping')
            ->where('customer_id', auth()->guard('api')->user()->id)
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'List Data My Orders: ' . auth()->guard('api')->user()->name,
            'data'    => $transactions,
        ], 200);
    }

    public function show($snap_token)
    {
        $transaction = Transaction::with('customer', 'shipping', 'transactionDetails.product')
            ->where('customer_id', auth()->guard('api')->user()->id)
            ->where('snap_token', $snap_token)
            ->first();

        if ($transaction) {
            return response()->json([
                'success' => true,
                'message' => 'Detail Data Transaction',
                'data'    => $transaction,
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Detail Data Transaction Tidak Ditemukan',
        ], 404);
    }
}
