<?php

namespace App\Orchid\Screens\Client;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Orchid\Filters\ClientSearchFilter;
use App\Orchid\Filters\ClientStatusFilter;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;

class ClientListScreen extends Screen
{
    public function name(): ?string
    {
        return 'Все клиенты';
    }

    public function query(): iterable
    {
        return [
            'clients' => Client::filters()
                ->defaultSort('created_at', 'desc')
                ->paginate(15),
        ];
    }

    public function commandBar(): iterable
    {
        return [
            Link::make('Создать клиента')
                ->icon('bs.person-plus')
                ->route('platform.clients.create')
                ->type(Color::PRIMARY),

            Link::make('📥 Экспорт Excel')
                ->icon('bs.download')
                ->href(route('platform.export.clients'))
                ->type(Color::SUCCESS),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::selection([ClientSearchFilter::class, ClientStatusFilter::class]),

            Layout::table('clients', [
                TD::make('id', 'ID')
                    ->sort(),

                TD::make('display_name', 'Имя')
                    ->render(fn (Client $client) => Link::make($client->display_name)
                        ->route('platform.clients.edit', $client)),

                TD::make('phone_number', 'Телефон')
                    ->sort(),

                TD::make('username', 'Telegram')
                    ->render(fn (Client $client) => $client->username 
                        ? "<a href='https://t.me/{$client->username}' target='_blank'>@{$client->username}</a>" 
                        : '-'),

                TD::make('status', 'Статус')
                    ->render(fn (Client $client) => 
                        "<span class='badge bg-{$client->status->color()}'>{$client->status->label()}</span>"),

                TD::make('activeSubscriptions', 'Подписки')
                    ->render(fn (Client $client) => $client->activeSubscriptions->count()),

                TD::make('created_at', 'Регистрация')
                    ->sort()
                    ->render(fn (Client $client) => $client->created_at->format('d.m.Y')),
            ]),
        ];
    }
}
