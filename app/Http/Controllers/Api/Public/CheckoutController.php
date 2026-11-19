<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Midtrans\Config;
use Midtrans\Snap;

class CheckoutController extends Controller implements HasMiddleware
{
    public $response;

    public static function middleware(): array
    {
        return [
            new Middleware('auth:api'),
        ];
    }

    public function __construct()
    {
        Config::$serverKey    = config('midtrans.server_key');
        Config::$isProduction = (bool) config('midtrans.is_production');
        Config::$isSanitized  = (bool) config('midtrans.is_sanitized');
        Config::$is3ds        = (bool) config('midtrans.is_3ds');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'province_name'    => 'nullable|string',
            'city_name'        => 'nullable|string',
            'district_name'    => 'nullable|string',
            'subdistrict_name' => 'nullable|string',
            'zip_code'         => 'nullable|string',
            'address'          => 'required|string',
            'weight'           => 'required|integer',
            'total'            => 'required|numeric',
            'shipping_courier' => 'required|string',
            'shipping_service' => 'required|string',
            'shipping_cost'    => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $customer = auth()->guard('api')->user();
        $carts = Cart::with('product')->where('customer_id', $customer->id)->get();

        if ($carts->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Keranjang belanja masih kosong!',
            ], 400);
        }

        DB::transaction(function () use ($request, $customer, $carts) {
            $invoice = 'INV-' . Str::upper(Str::random(10));

            $transaction = Transaction::create([
                'customer_id'      => $customer->id,
                'invoice'          => $invoice,
                'province_name'    => $request->province_name,
                'city_name'        => $request->city_name,
                'district_name'    => $request->district_name,
                'subdistrict_name' => $request->subdistrict_name,
                'zip_code'         => $request->zip_code,
                'address'          => $request->address,
                'weight'           => $request->weight,
                'total'            => $request->total,
                'status'           => 'pending',
            ]);

            $transaction->shipping()->create([
                'shipping_courier' => $request->shipping_courier,
                'shipping_service' => $request->shipping_service,
                'shipping_cost'    => $request->shipping_cost,
            ]);

            $itemDetails = [];

            foreach ($carts as $cart) {
                $transaction->transactionDetails()->create([
                    'product_id' => $cart->product_id,
                    'qty'        => $cart->qty,
                    'price'      => $cart->product->price * $cart->qty,
                ]);

                $itemDetails[] = [
                    'id'       => (string) $cart->product_id,
                    'price'    => (int) $cart->product->price,
                    'quantity' => (int) $cart->qty,
                    'name'     => Str::limit($cart->product->title, 50, ''),
                ];
            }

            $itemDetails[] = [
                'id'       => 'SHIPPING',
                'price'    => (int) $request->shipping_cost,
                'quantity' => 1,
                'name'     => 'Ongkos Kirim (' . strtoupper($request->shipping_courier) . ' - ' . $request->shipping_service . ')',
            ];

            Cart::where('customer_id', $customer->id)->delete();

            $payload = [
                'transaction_details' => [
                    'order_id'     => $transaction->invoice,
                    'gross_amount' => (int) $transaction->total,
                ],
                'customer_details' => [
                    'first_name' => $customer->name,
                    'email'      => $customer->email,
                ],
                'item_details' => $itemDetails,
            ];

            $snapToken = Snap::getSnapToken($payload);

            $transaction->snap_token = $snapToken;
            $transaction->save();

            $this->response['snap_token'] = $snapToken;
        });

        return response()->json([
            'success' => true,
            'message' => 'Checkout Berhasil!',
            'data'    => $this->response,
        ], 201);
    }
}
