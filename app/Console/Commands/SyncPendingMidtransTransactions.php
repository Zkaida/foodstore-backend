<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\MidtransPaymentStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncPendingMidtransTransactions extends Command
{
    protected $signature = 'midtrans:sync-pending {--limit=50}';

    protected $description = 'Synchronize pending orders with Midtrans transaction status';

    public function handle(MidtransPaymentStatus $paymentStatus): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 500) {
            $this->error('The limit must be an integer between 1 and 500.');

            return self::INVALID;
        }

        $updated = 0;
        $failed = 0;
        $cursor = (int) Cache::get('midtrans:pending-sync:last-transaction-id', 0);

        $transactions = Transaction::where('status', 'pending')
            ->where('id', '>', $cursor)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($transactions->isEmpty() && $cursor > 0) {
            $transactions = Transaction::where('status', 'pending')
                ->orderBy('id')
                ->limit($limit)
                ->get();
        }

        foreach ($transactions as $transaction) {
            try {
                if ($paymentStatus->refresh($transaction)) {
                    $updated++;
                }
            } catch (\Exception $exception) {
                Log::warning('Unable to synchronize a pending Midtrans transaction.', [
                    'transaction_id' => $transaction->id,
                    'exception' => $exception,
                ]);
                $failed++;
            }

            Cache::put('midtrans:pending-sync:last-transaction-id', $transaction->id);
        }

        $this->info("Midtrans status synchronization finished: {$updated} updated, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
