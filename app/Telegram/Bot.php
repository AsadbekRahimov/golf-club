<?php

namespace App\Telegram;

use App\Models\Client;
use App\Telegram\Handlers\CallbackHandler;
use App\Telegram\Handlers\FileHandler;
use App\Telegram\Handlers\MessageHandler;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

class Bot
{
    protected Api $telegram;
    protected ?Update $update = null;
    protected ?Client $client = null;

    public function __construct()
    {
        $this->telegram = new Api(config('telegram.bots.golfclub.token'));
    }

    public function handleUpdate(array $data): void
    {
        $this->update = new Update($data);
        
        Log::channel('single')->debug('Telegram update', $data);

        $this->client = $this->identifyClient($data);

        if ($this->update->isType('message')) {
            $this->handleMessage();
        } elseif ($this->update->isType('callback_query')) {
            $this->handleCallback();
        }
    }

    protected function identifyClient(array $rawData): ?Client
    {
        $telegramId = null;
        
        // Get telegram_id from raw data to avoid SDK parsing issues
        if (isset($rawData['message']['from']['id'])) {
            $telegramId = $rawData['message']['from']['id'];
        } elseif (isset($rawData['callback_query']['from']['id'])) {
            $telegramId = $rawData['callback_query']['from']['id'];
        }

        if (!$telegramId) {
            Log::channel('single')->warning('No telegram_id found in update');
            return null;
        }

        $client = Client::where('telegram_id', $telegramId)->first();
        
        if (!$client) {
            Log::channel('single')->warning('Client not found', [
                'telegram_id' => $telegramId,
            ]);
        }

        return $client;
    }

    protected function handleMessage(): void
    {
        $message = $this->update->getMessage();
        
        if ($message->has('contact')) {
            (new MessageHandler($this->telegram, $this->update, $this->client))
                ->handleContact($message->getContact());
            return;
        }

        if ($message->has('photo') || $message->has('document')) {
            (new FileHandler($this->telegram, $this->update, $this->client))
                ->handle();
            return;
        }

        $text = $message->getText() ?? '';
        
        if (str_starts_with($text, '/')) {
            (new MessageHandler($this->telegram, $this->update, $this->client))
                ->handleCommand($text);
            return;
        }

        (new MessageHandler($this->telegram, $this->update, $this->client))
            ->handleText($text);
    }

    protected function handleCallback(): void
    {
        (new CallbackHandler($this->telegram, $this->update, $this->client))
            ->handle();
    }

    public function getTelegram(): Api
    {
        return $this->telegram;
    }
}
