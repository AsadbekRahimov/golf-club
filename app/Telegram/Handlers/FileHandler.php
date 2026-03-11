<?php

namespace App\Telegram\Handlers;

use App\Models\Client;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

class FileHandler
{
    public function __construct(
        protected Api $telegram,
        protected Update $update,
        protected ?Client $client
    ) {}

    public function handle(): void
    {
        // File uploads are not used in this system
    }
}
