<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;

class CallbackController extends Controller
{
    public function __invoke(Request $request)
    {
        $payload = $request->getContent();
        $notification = json_decode($payload);

        if (!$notification) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        $validSignatureKey = hash(
            'sha512',
            $notification->order_id .
            $notification->status_code .
            $notification->gross_amount .
            config('midtrans.server_key')
        );

        if ($notification->signature_key != $validSignatureKey) {
            return response()->json([
                'message' => 'Invalid signature',
            ], 403);
        }

        $transaction = $notification->transaction_status;
        $type = $notification->payment_type;
        $orderId = $notification->order_id;

        $data_transaction = Transaction::where('invoice', $orderId)->first();

        if (!$data_transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        if ($transaction == 'capture') {
            if ($type == 'credit_card') {
                $data_transaction->update([
                    'status' => 'success',
                ]);
            }
        } elseif ($transaction == 'settlement') {
            $data_transaction->update([
                'status' => 'success',
            ]);
        } elseif ($transaction == 'pending') {
            $data_transaction->update([
                'status' => 'pending',
            ]);
        } elseif ($transaction == 'deny') {
            $data_transaction->update([
                'status' => 'failed',
            ]);
        } elseif ($transaction == 'expire') {
            $data_transaction->update([
                'status' => 'expired',
            ]);
        } elseif ($transaction == 'cancel') {
            $data_transaction->update([
                'status' => 'failed',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Callback processed successfully',
        ], 200);
    }
}
