<?php

declare(strict_types=1);

namespace App\Orchid;

use App\Models\BookingRequest;
use App\Models\Client;
use Orchid\Platform\Dashboard;
use Orchid\Platform\ItemPermission;
use Orchid\Platform\OrchidServiceProvider;
use Orchid\Screen\Actions\Menu;
use Orchid\Support\Color;

class PlatformProvider extends OrchidServiceProvider
{
    public function boot(Dashboard $dashboard): void
    {
        parent::boot($dashboard);
    }

    public function menu(): array
    {
        return [
            Menu::make('Панель управления')
                ->icon('bs.speedometer2')
                ->route('platform.dashboard')
                ->title('Главная'),

            Menu::make('Новые заявки')
                ->icon('bs.person-plus')
                ->route('platform.clients.pending')
                ->badge(fn () => Client::pending()->count() ?: null, Color::WARNING)
                ->title('Клиенты'),

            Menu::make('Все клиенты')
                ->icon('bs.people')
                ->route('platform.clients'),

            Menu::make('Ожидающие')
                ->icon('bs.hourglass-split')
                ->route('platform.bookings.pending')
                ->badge(fn () => BookingRequest::awaitingAction()->count() ?: null, Color::INFO)
                ->title('Бронирования'),

            Menu::make('Все бронирования')
                ->icon('bs.journal-text')
                ->route('platform.bookings'),

            Menu::make('Шкафы')
                ->icon('bs.archive')
                ->route('platform.lockers')
                ->title('Управление'),

            Menu::make('Подписки')
                ->icon('bs.card-checklist')
                ->route('platform.subscriptions'),

            Menu::make('Настройки')
                ->icon('bs.gear')
                ->route('platform.settings')
                ->divider(),

            Menu::make(__('Пользователи'))
                ->icon('bs.person-gear')
                ->route('platform.systems.users')
                ->permission('platform.systems.users')
                ->title(__('Система')),

            Menu::make(__('Роли'))
                ->icon('bs.shield')
                ->route('platform.systems.roles')
                ->permission('platform.systems.roles'),
        ];
    }

    public function permissions(): array
    {
        return [
            ItemPermission::group(__('System'))
                ->addPermission('platform.systems.roles', __('Roles'))
                ->addPermission('platform.systems.users', __('Users')),
        ];
    }
}
