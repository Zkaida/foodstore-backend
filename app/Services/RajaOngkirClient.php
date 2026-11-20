<?php

namespace App\Services;

use App\Exceptions\RajaOngkirApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RajaOngkirClient
{
    public function searchDestination(string $search, int $limit = 10, int $offset = 0): array
    {
        $search = trim($search);
        $cacheKey = 'rajaongkir.destination-search.v2.'.hash(
            'sha256',
            Str::lower($search).'|'.$limit.'|'.$offset
        );
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $response = Http::withHeaders([
            'key' => config('rajaongkir.api_key'),
        ])->timeout(15)->get('https://rajaongkir.komerce.id/api/v1/destination/domestic-destination', [
            'search' => $search,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        $result = [
            'body' => $response->json(),
            'status' => $this->responseStatus($response),
        ];

        if ($result['status'] < 400 && is_array(data_get($result['body'], 'data'))) {
            Cache::put($cacheKey, $result, now()->addHours(12));
        }

        return $result;
    }

    public function responseStatus(Response $response): int
    {
        if (! $response->successful()) {
            return $response->status();
        }

        $providerCode = $response->json('meta.code');
        if (is_numeric($providerCode) && (int) $providerCode >= 400 && (int) $providerCode <= 599) {
            return (int) $providerCode;
        }

        return $response->json('meta.status') === 'error' ? 502 : $response->status();
    }

    public function calculateDomesticCost(int $destination, int $weight, string $courier): Response
    {
        return Http::asForm()->withHeaders([
            'key' => config('rajaongkir.api_key'),
        ])->post('https://rajaongkir.komerce.id/api/v1/calculate/domestic-cost', [
            'origin' => (int) config('rajaongkir.origin'),
            'destination' => $destination,
            'weight' => $weight,
            'courier' => strtolower($courier),
        ]);
    }

    public function resolveDestinationId(array $address): ?int
    {
        $search = $address['subdistrict_name'] ?? $address['zip_code'] ?? null;
        if (! is_string($search) || trim($search) === '') {
            return null;
        }

        $response = $this->searchDestination($search, 100);
        if ($response['status'] >= 400) {
            throw new RajaOngkirApiException($response['status']);
        }

        $destinations = data_get($response['body'], 'data');
        if (! is_array($destinations)) {
            throw new \UnexpectedValueException('RajaOngkir returned an invalid destination response.');
        }

        $locationFields = [
            'subdistrict_name',
            'district_name',
            'city_name',
            'province_name',
            'zip_code',
        ];

        $matches = collect($destinations)->filter(function ($destination) use ($address, $locationFields) {
            if (! is_array($destination)) {
                return false;
            }

            foreach ($locationFields as $field) {
                $expected = $address[$field] ?? null;
                if ($expected !== null && trim((string) $expected) !== ''
                    && Str::upper(trim((string) ($destination[$field] ?? ''))) !== Str::upper(trim((string) $expected))) {
                    return false;
                }
            }

            return isset($destination['id']) && filter_var($destination['id'], FILTER_VALIDATE_INT) !== false;
        })->values();

        return $matches->count() === 1 ? (int) $matches->first()['id'] : null;
    }
}
