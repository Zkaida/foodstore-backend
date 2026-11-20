<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\MidtransPaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CallbackController extends Controller
{
    public function __invoke(Request $request, MidtransPaymentStatus $paymentStatus)
    {
        $notification = json_decode($request->getContent(), true);

        if (! is_array($notification)) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key', 'transaction_status', 'payment_type'] as $field) {
            if (! isset($notification[$field]) || ! is_scalar($notification[$field])) {
                return response()->json(['message' => 'Invalid payload'], 400);
            }
        }

        if (
            $notification['order_id'] === '' ||
            ! is_numeric($notification['status_code']) ||
            ! is_numeric($notification['gross_amount'])
        ) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        $validSignatureKey = hash(
            'sha512',
            $notification['order_id'].
            $notification['status_code'].
            $notification['gross_amount'].
            config('midtrans.server_key')
        );

        if (! hash_equals($validSignatureKey, (string) $notification['signature_key'])) {
            Log::warning('Midtrans callback signature validation failed.', [
                'order_id' => $notification['order_id'],
            ]);

            return response()->json([
                'message' => 'Invalid signature',
            ], 403);
        }

        $data_transaction = Transaction::where('invoice', $notification['order_id'])->first();

        if (! $data_transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        if ($paymentStatus->apply(
            $data_transaction,
            (string) $notification['transaction_status'],
            (string) $notification['payment_type']
        )) {
            Log::info('Midtrans transaction status updated.', [
                'order_id' => $data_transaction->invoice,
                'status' => $data_transaction->status,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Callback processed successfully',
        ], 200);
    }
}
