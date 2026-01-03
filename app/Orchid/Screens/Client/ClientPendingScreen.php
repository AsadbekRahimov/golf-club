<?php

namespace App\Orchid\Screens\Client;

use App\Models\Client;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;

class ClientPendingScreen extends Screen
{
    public function name(): ?string
    {
        return 'Новые заявки на регистрацию';
    }

    public function description(): ?string
    {
        return 'Клиенты, ожидающие подтверждения';
    }

    public function query(): iterable
    {
        return [
            'clients' => Client::pending()
                ->orderBy('created_at', 'desc')
                ->paginate(15),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::table('clients', [
                TD::make('display_name', 'Имя')
                    ->render(fn (Client $client) => Link::make($client->display_name)
                        ->route('platform.clients.edit', $client)),

                TD::make('phone_number', 'Телефон'),

                TD::make('username', 'Telegram')
                    ->render(fn (Client $client) => $client->username 
                        ? "<a href='https://t.me/{$client->username}' target='_blank'>@{$client->username}</a>" 
                        : '-'),

                TD::make('created_at', 'Дата заявки')
                    ->render(fn (Client $client) => $client->created_at->format('d.m.Y H:i')),

                TD::make('action', 'Действия')
                    ->render(fn (Client $client) => Link::make('Рассмотреть')
                        ->class('btn btn-sm btn-primary')
                        ->route('platform.clients.edit', $client)),
            ]),
        ];
    }
}
