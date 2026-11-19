<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RajaOngkirController extends Controller
{
    public function searchDestination(Request $request)
    {
        $response = Http::withoutVerifying()->withHeaders([
            'key' => config('rajaongkir.api_key'),
        ])->get('https://rajaongkir.komerce.id/api/v1/destination/domestic-destination', [
            'search' => $request->search,
            'limit'  => $request->limit ?? 10,
            'offset' => $request->offset ?? 0,
        ]);
    
        return response()->json($response->json(), $response->status());
    }
    
    public function checkOngkir(Request $request)
    {
        $response = Http::withoutVerifying()->asForm()->withHeaders([
            'key' => config('rajaongkir.api_key'),
        ])->post('https://rajaongkir.komerce.id/api/v1/calculate/domestic-cost', [
            'origin'      => $request->origin,
            'destination' => $request->destination,
            'weight'      => $request->weight,
            'courier'     => $request->courier,
        ]);

        return response()->json($response->json(), $response->status());
    }
}
