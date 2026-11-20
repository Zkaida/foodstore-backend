<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Transaction;
use App\Services\RajaOngkirClient;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Midtrans\Config;
use Midtrans\Snap;

class CheckoutController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth:api'),
        ];
    }

    public function __construct()
    {
        Config::$serverKey = config('midtrans.server_key') ?: env('MIDTRANS_SERVER_KEY');
        Config::$isProduction = (bool) config('midtrans.is_production', false);
        Config::$isSanitized = (bool) config('midtrans.is_sanitized', true);
        Config::$is3ds = (bool) config('midtrans.is_3ds', true);
        Config::$overrideNotifUrl = config('midtrans.notification_url') ?: null;

        Config::$curlOptions = [
            CURLOPT_HTTPHEADER => [],
        ];
    }

    public function store(Request $request, RajaOngkirClient $rajaOngkir)
    {
        $validator = Validator::make($request->all(), [
            'province_name' => 'nullable|string',
            'city_name' => 'nullable|string',
            'district_name' => 'nullable|string',
            'subdistrict_name' => 'nullable|string',
            'zip_code' => 'nullable|string',
            'full_address' => 'required_without:address|string',
            'address' => 'nullable|string',
            'destination' => 'nullable|integer|min:1',
            'shipping_courier' => 'required|string',
            'shipping_service' => 'required|string',
            'shipping_cost' => 'nullable|numeric',
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

        $productsTotal = $carts->sum(fn ($cart) => $cart->product->price * $cart->qty);
        $totalWeight = max(1, (int) $carts->sum(fn ($cart) => $cart->product->weight * $cart->qty));
        $courier = strtolower($request->shipping_courier);
        $service = $request->shipping_service;

        // 1. Tentukan destinationId dengan fallback aman jika API RajaOngkir gagal/SSL error
        $destinationId = $request->input('destination');
        if (! $destinationId) {
            try {
                $destinationId = $rajaOngkir->resolveDestinationId($validator->validated());
            } catch (\Throwable $e) {
                $destinationId = 31590; // Fallback ID tujuan default
            }
        }
        $destinationId = $destinationId ?: 31590;

        // 2. Hitung & verifikasi ongkos kirim dengan fallback aman
        $shippingCost = null;
        $apiKey = config('rajaongkir.api_key') ?: env('RAJAONGKIR_API_KEY');

        try {
            $response = Http::withoutVerifying()
                ->timeout(10)
                ->asForm()
                ->withHeaders(['key' => $apiKey])
                ->post('https://rajaongkir.komerce.id/api/v1/calculate/domestic-cost', [
                    'origin' => config('rajaongkir.origin', 31590),
                    'destination' => (int) $destinationId,
                    'weight' => $totalWeight,
                    'courier' => $courier,
                ]);

            if ($response->successful() && is_array($response->json('data'))) {
                $shippingRate = collect($response->json('data'))->first(
                    fn ($rate) => is_array($rate) && ($rate['service'] ?? null) === $service
                );
                if ($shippingRate && isset($shippingRate['cost'])) {
                    $shippingCost = (int) $shippingRate['cost'];
                }
            }
        } catch (\Throwable $e) {
            // Lanjut ke fallback ongkir di bawah
        }

        // Fallback ongkir jika API eksternal tidak merespons / menggunakan tarif fallback
        if ($shippingCost === null) {
            if ($request->filled('shipping_cost') && (int) $request->shipping_cost > 0) {
                $shippingCost = (int) $request->shipping_cost;
            } else {
                $shippingCost = strtoupper($service) === 'YES' ? 22000 : 12000;
            }
        }

        $total = (int) ($productsTotal + $shippingCost);

        $snapToken = DB::transaction(function () use ($request, $customer, $carts, $totalWeight, $shippingCost, $total, $courier, $service) {
            $invoice = 'INV-'.Str::upper(Str::random(10));

            $transaction = Transaction::create([
                'customer_id' => $customer->id,
                'invoice' => $invoice,
                'province_name' => $request->province_name ?? 'JAWA TENGAH',
                'city_name' => $request->city_name ?? 'SALATIGA',
                'district_name' => $request->district_name ?? 'SIDOREJO',
                'subdistrict_name' => $request->subdistrict_name ?? 'SIDOREJO',
                'zip_code' => $request->zip_code ?? '50711',
                'address' => $request->input('full_address') ?? $request->input('address') ?? '-',
                'weight' => $totalWeight,
                'total' => $total,
                'status' => 'pending',
            ]);

            $transaction->shipping()->create([
                'shipping_courier' => $courier,
                'shipping_service' => $service,
                'shipping_cost' => $shippingCost,
            ]);

            $itemDetails = [];

            foreach ($carts as $cart) {
                $transaction->transactionDetails()->create([
                    'product_id' => $cart->product_id,
                    'qty' => $cart->qty,
                    'price' => $cart->product->price * $cart->qty,
                ]);

                $itemDetails[] = [
                    'id' => (string) $cart->product_id,
                    'price' => (int) $cart->product->price,
                    'quantity' => (int) $cart->qty,
                    'name' => Str::limit($cart->product->title, 50, ''),
                ];
            }

            $itemDetails[] = [
                'id' => 'SHIPPING',
                'price' => $shippingCost,
                'quantity' => 1,
                'name' => 'Ongkos Kirim ('.strtoupper($courier).' - '.$service.')',
            ];

            Cart::where('customer_id', $customer->id)->delete();

            $payload = [
                'transaction_details' => [
                    'order_id' => $transaction->invoice,
                    'gross_amount' => $total,
                ],
                'customer_details' => [
                    'first_name' => $customer->name,
                    'email' => $customer->email,
                ],
                'item_details' => $itemDetails,
            ];

            $snapToken = Snap::getSnapToken($payload);

            $transaction->snap_token = $snapToken;
            $transaction->save();

            return $snapToken;
        });

        return response()->json([
            'success' => true,
            'message' => 'Checkout Berhasil!',
            'data' => ['snap_token' => $snapToken],
        ], 201);
    }
}
