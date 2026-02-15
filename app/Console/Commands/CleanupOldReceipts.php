<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupOldReceipts extends Command
{
    protected $signature = 'receipts:cleanup {--days=90 : Delete receipts older than N days}';

    protected $description = 'Delete receipt files older than specified days';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoffDate = now()->subDays($days);

        $payments = Payment::whereNotNull('receipt_file_path')
            ->where('created_at', '<', $cutoffDate)
            ->get();

        $deleted = 0;

        foreach ($payments as $payment) {
            if ($payment->receipt_file_path && Storage::disk('public')->exists($payment->receipt_file_path)) {
                Storage::disk('public')->delete($payment->receipt_file_path);
                $deleted++;
            }

            $payment->update([
                'receipt_file_path' => null,
                'receipt_file_name' => null,
                'receipt_file_type' => null,
            ]);
        }

        $this->info("Deleted {$deleted} receipt files older than {$days} days.");

        return self::SUCCESS;
    }
}
