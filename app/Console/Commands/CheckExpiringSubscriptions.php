<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class CheckExpiringSubscriptions extends Command
{
    protected $signature = 'subscriptions:check-expiring';
    protected $description = 'Check and notify clients about expiring subscriptions';

    public function handle(SubscriptionService $subscriptionService): int
    {
        $this->info('Checking expiring subscriptions...');

        $expiring = $subscriptionService->checkExpiring();

        $this->info("Notified {$expiring->count()} clients about expiring subscriptions.");

        return 0;
    }
}
