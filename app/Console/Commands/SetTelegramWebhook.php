<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Telegram\Bot\Api;

class SetTelegramWebhook extends Command
{
    protected $signature = 'telegram:set-webhook {--remove : Remove the webhook}';
    protected $description = 'Set or remove Telegram webhook';

    public function handle(): int
    {
        $token = config('telegram.bots.golfclub.token');
        
        if (!$token) {
            $this->error('Telegram bot token is not configured.');
            return 1;
        }

        $telegram = new Api($token);

        if ($this->option('remove')) {
            $telegram->removeWebhook();
            $this->info('Webhook removed successfully.');
            return 0;
        }

        $webhookUrl = config('telegram.bots.golfclub.webhook_url');
        
        if (!$webhookUrl) {
            $this->error('Webhook URL is not configured.');
            return 1;
        }

        $params = ['url' => $webhookUrl];

        $secret = config('telegram.webhook_secret');
        if ($secret) {
            $params['secret_token'] = $secret;
        }

        $result = $telegram->setWebhook($params);

        if ($result) {
            $this->info('Webhook set successfully: ' . $webhookUrl);
        } else {
            $this->error('Failed to set webhook.');
            return 1;
        }

        return 0;
    }
}
