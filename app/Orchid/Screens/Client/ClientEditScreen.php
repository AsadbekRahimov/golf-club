<?php

namespace App\Orchid\Screens\Client;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Services\ClientService;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Screen\Sight;
use Orchid\Screen\TD;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class ClientEditScreen extends Screen
{
    public ?Client $client = null;

    public function name(): ?string
    {
        return $this->client?->display_name ?? 'Клиент';
    }

    public function description(): ?string
    {
        return 'Управление клиентом';
    }

    public function query(Client $client): iterable
    {
        $client->load(['subscriptions.locker', 'bookingRequests', 'approvedBy']);

        return [
            'client' => $client,
            'subscriptions' => $client->activeSubscriptions,
        ];
    }

    public function commandBar(): iterable
    {
        return [
            Button::make('Подтвердить')
                ->icon('bs.check-circle')
                ->type(Color::SUCCESS)
                ->method('approve')
                ->canSee($this->client?->isPending()),

            Button::make('Отклонить')
                ->icon('bs.x-circle')
                ->type(Color::DANGER)
                ->method('reject')
                ->canSee($this->client?->isPending()),

            Button::make('Заблокировать')
                ->icon('bs.lock')
                ->type(Color::DANGER)
                ->method('block')
                ->canSee($this->client?->isApproved()),

            Button::make('Разблокировать')
                ->icon('bs.unlock')
                ->type(Color::SUCCESS)
                ->method('unblock')
                ->canSee($this->client?->isBlocked()),

            Link::make('Назад')
                ->icon('bs.arrow-left')
                ->route('platform.clients'),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::legend('client', [
                Sight::make('status', 'Статус')
                    ->render(fn (Client $client) => 
                        "<span class='badge bg-{$client->status->color()}'>{$client->status->label()}</span>"),
                Sight::make('phone_number', 'Телефон'),
                Sight::make('telegram_link', 'Telegram')
                    ->render(fn (Client $client) => $client->username 
                        ? "<a href='https://t.me/{$client->username}' target='_blank'>@{$client->username}</a>" 
                        : 'Не указан'),
                Sight::make('display_name', 'ФИО'),
                Sight::make('created_at', 'Дата регистрации')
                    ->render(fn (Client $client) => $client->created_at->format('d.m.Y H:i')),
                Sight::make('approved_at', 'Дата подтверждения')
                    ->render(fn (Client $client) => $client->approved_at?->format('d.m.Y H:i') ?? '-'),
                Sight::make('approvedBy.name', 'Подтвердил'),
            ])->title('Информация о клиенте'),

            Layout::rows([
                Input::make('client.full_name')
                    ->title('ФИО клиента (переопределить)')
                    ->placeholder($this->client?->display_name ?? 'Введите полное имя')
                    ->help('Оставьте пустым, чтобы использовать имя из Telegram'),

                TextArea::make('client.notes')
                    ->title('Заметки')
                    ->rows(3)
                    ->placeholder('Заметки о клиенте'),

                Button::make('Сохранить')
                    ->icon('bs.check')
                    ->type(Color::PRIMARY)
                    ->method('save'),
            ])->title('Редактирование'),

            Layout::table('subscriptions', [
                TD::make('subscription_type', 'Тип')
                    ->render(fn ($sub) => $sub->subscription_type->label()),
                TD::make('locker.locker_number', 'Шкаф')
                    ->render(fn ($sub) => $sub->locker ? "#{$sub->locker->locker_number}" : '-'),
                TD::make('start_date', 'Начало')
                    ->render(fn ($sub) => $sub->start_date->format('d.m.Y')),
                TD::make('end_date', 'Окончание')
                    ->render(fn ($sub) => $sub->end_date?->format('d.m.Y') ?? 'Бессрочно'),
                TD::make('status', 'Статус')
                    ->render(fn ($sub) => "<span class='badge bg-{$sub->status->color()}'>{$sub->status->label()}</span>"),
            ])->title('Активные подписки'),
        ];
    }

    public function save(Client $client, Request $request): void
    {
        $client->update($request->input('client'));

        Toast::info('Данные клиента сохранены');
    }

    public function approve(Client $client, ClientService $clientService): void
    {
        $clientService->approve($client, auth()->user());

        Toast::success('Клиент подтвержден, уведомление отправлено');
    }

    public function reject(Client $client, ClientService $clientService): void
    {
        $clientService->reject($client, 'Отклонено администратором');

        Toast::warning('Клиент отклонен, уведомление отправлено');
    }

    public function block(Client $client, ClientService $clientService): void
    {
        $clientService->block($client, 'Заблокирован администратором');

        Toast::warning('Клиент заблокирован');
    }

    public function unblock(Client $client, ClientService $clientService): void
    {
        $clientService->unblock($client);

        Toast::success('Клиент разблокирован');
    }
}
