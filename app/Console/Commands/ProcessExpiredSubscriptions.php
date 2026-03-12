<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class ProcessExpiredSubscriptions extends Command
{
    protected $signature = 'subscriptions:process-expired';
    protected $description = 'Process expired subscriptions and release lockers';

    public function handle(SubscriptionService $subscriptionService): int
    {
        $this->info('Processing expired subscriptions...');

        $count = $subscriptionService->processExpired();

        if ($count > 0) {
            $this->info("Processed {$count} expired subscriptions.");
            $this->info('Notifications sent to clients and admins.');
        } else {
            $this->info('No expired subscriptions found.');
        }

        return 0;
    }
}
