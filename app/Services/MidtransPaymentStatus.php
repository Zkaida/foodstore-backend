<?php

namespace App\Services;

use App\Models\Transaction as Order;
use Midtrans\Config;
use Midtrans\Transaction as MidtransTransaction;
use UnexpectedValueException;

class MidtransPaymentStatus
{
    public function refresh(Order $order): bool
    {
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = (bool) config('midtrans.is_production');
        Config::$curlOptions[CURLOPT_HTTPHEADER] = Config::$curlOptions[CURLOPT_HTTPHEADER] ?? [];
        Config::$curlOptions[CURLOPT_CONNECTTIMEOUT] = 10;
        Config::$curlOptions[CURLOPT_TIMEOUT] = 30;

        $remote = MidtransTransaction::status($order->invoice);

        if (
            ! is_object($remote) ||
            (string) ($remote->order_id ?? '') !== $order->invoice ||
            ! is_numeric($remote->gross_amount ?? null) ||
            ! is_string($remote->transaction_status ?? null) ||
            ! is_string($remote->payment_type ?? null)
        ) {
            throw new UnexpectedValueException('Midtrans returned an invalid transaction status.');
        }

        if (number_format((float) $remote->gross_amount, 2, '.', '') !== number_format((float) $order->total, 2, '.', '')) {
            throw new UnexpectedValueException('Midtrans transaction amount does not match the stored order.');
        }

        return $this->apply($order, $remote->transaction_status, $remote->payment_type);
    }

    public function apply(Order $order, string $midtransStatus, string $paymentType): bool
    {
        $status = match ($midtransStatus) {
            'capture' => $paymentType === 'credit_card' ? 'success' : null,
            'settlement' => 'success',
            'pending' => 'pending',
            'deny', 'cancel' => 'failed',
            'expire' => 'expired',
            default => null,
        };

        if ($status === null || ($order->status !== 'pending' && $status !== 'success') || $order->status === $status) {
            return false;
        }

        $order->update(['status' => $status]);

        return true;
    }
}
