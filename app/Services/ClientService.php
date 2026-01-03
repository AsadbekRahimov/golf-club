<?php

namespace App\Services;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\User;

class ClientService
{
    public function __construct(
        protected TelegramService $telegramService
    ) {}

    public function register(array $data): Client
    {
        $client = Client::create([
            'phone_number' => $this->normalizePhone($data['phone_number']),
            'telegram_id' => $data['telegram_id'],
            'telegram_chat_id' => $data['telegram_chat_id'],
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'username' => $data['username'] ?? null,
            'status' => ClientStatus::PENDING,
        ]);

        $this->telegramService->notifyAdmins(
            "🆕 *Новая заявка на регистрацию*\n\n" .
            "👤 {$client->display_name}\n" .
            "📱 {$client->phone_number}\n" .
            "🕐 {$client->created_at->format('d.m.Y H:i')}"
        );

        return $client;
    }

    public function approve(Client $client, User $admin): Client
    {
        $client->update([
            'status' => ClientStatus::APPROVED,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $this->telegramService->notifyClientApproved($client);

        return $client->fresh();
    }

    public function reject(Client $client, ?string $reason = null): Client
    {
        $client->update([
            'status' => ClientStatus::BLOCKED,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $this->telegramService->notifyClientRejected($client);

        return $client->fresh();
    }

    public function block(Client $client, ?string $reason = null): Client
    {
        $client->update([
            'status' => ClientStatus::BLOCKED,
            'notes' => $reason ? ($client->notes . "\nЗаблокирован: " . $reason) : $client->notes,
        ]);

        return $client->fresh();
    }

    public function unblock(Client $client): Client
    {
        $client->update([
            'status' => ClientStatus::APPROVED,
        ]);

        return $client->fresh();
    }

    public function findByPhone(string $phone): ?Client
    {
        return Client::where('phone_number', $this->normalizePhone($phone))->first();
    }

    public function findByTelegramId(int $telegramId): ?Client
    {
        return Client::where('telegram_id', $telegramId)->first();
    }

    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^\d+]/', '', $phone);
        
        if (preg_match('/^\+?998(\d{2})(\d{3})(\d{2})(\d{2})$/', $phone, $matches)) {
            return "+998 {$matches[1]} {$matches[2]}-{$matches[3]}-{$matches[4]}";
        }

        return $phone;
    }

    public function getStatistics(): array
    {
        return [
            'total' => Client::count(),
            'pending' => Client::pending()->count(),
            'approved' => Client::approved()->count(),
            'blocked' => Client::blocked()->count(),
            'with_active_subscriptions' => Client::whereHas('activeSubscriptions')->count(),
        ];
    }
}
