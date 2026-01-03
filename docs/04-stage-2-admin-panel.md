# Этап 2: Администраторская панель (Laravel Orchid)

## Обзор этапа

**Цель:** Создать полнофункциональную админ-панель для управления всеми аспектами системы.

**Длительность:** 5-7 дней

**Зависимости:** Этап 1 (База данных)

**Результат:** Работающая админ-панель со всеми экранами и функциями.

---

## Чек-лист задач

- [ ] Настроить меню навигации в PlatformProvider
- [ ] Создать Dashboard с общей статистикой
- [ ] Создать экраны управления клиентами
- [ ] Создать экраны обработки бронирований
- [ ] Создать экраны проверки платежей
- [ ] Создать экраны управления шкафами
- [ ] Создать экраны управления подписками
- [ ] Создать экран настроек системы
- [ ] Добавить фильтры и поиск
- [ ] Настроить права доступа

---

## 1. Настройка PlatformProvider

### 1.1 Меню навигации

**Файл:** `app/Orchid/PlatformProvider.php`

```php
<?php

namespace App\Orchid;

use App\Models\BookingRequest;
use App\Models\Client;
use App\Models\Payment;
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
            Menu::make('Dashboard')
                ->icon('bs.speedometer2')
                ->route('platform.dashboard')
                ->title('Навигация'),

            Menu::make('Клиенты')
                ->icon('bs.people')
                ->list([
                    Menu::make('Все клиенты')
                        ->icon('bs.person-lines-fill')
                        ->route('platform.clients'),
                    
                    Menu::make('Ожидают подтверждения')
                        ->icon('bs.person-plus')
                        ->route('platform.clients.pending')
                        ->badge(fn() => Client::pending()->count() ?: null, Color::WARNING),
                ]),

            Menu::make('Бронирования')
                ->icon('bs.calendar-check')
                ->route('platform.bookings')
                ->badge(fn() => BookingRequest::awaitingAction()->count() ?: null, Color::INFO),

            Menu::make('Платежи')
                ->icon('bs.credit-card')
                ->route('platform.payments')
                ->badge(fn() => Payment::pending()->count() ?: null, Color::WARNING),

            Menu::make('Шкафы')
                ->icon('bs.archive')
                ->route('platform.lockers'),

            Menu::make('Подписки')
                ->icon('bs.card-checklist')
                ->route('platform.subscriptions'),

            Menu::make('Настройки')
                ->icon('bs.gear')
                ->route('platform.settings')
                ->title('Система'),

            Menu::make('Пользователи')
                ->icon('bs.person-gear')
                ->route('platform.systems.users')
                ->permission('platform.systems.users'),

            Menu::make('Роли')
                ->icon('bs.shield')
                ->route('platform.systems.roles')
                ->permission('platform.systems.roles'),
        ];
    }

    public function permissions(): array
    {
        return [
            ItemPermission::group('Система')
                ->addPermission('platform.systems.roles', 'Управление ролями')
                ->addPermission('platform.systems.users', 'Управление пользователями'),

            ItemPermission::group('Гольф-клуб')
                ->addPermission('platform.clients', 'Управление клиентами')
                ->addPermission('platform.bookings', 'Управление бронированиями')
                ->addPermission('platform.payments', 'Управление платежами')
                ->addPermission('platform.lockers', 'Управление шкафами')
                ->addPermission('platform.subscriptions', 'Управление подписками')
                ->addPermission('platform.settings', 'Настройки системы'),
        ];
    }
}
```

### 1.2 Маршруты

**Файл:** `routes/platform.php`

```php
<?php

use App\Orchid\Screens\Dashboard\DashboardScreen;
use App\Orchid\Screens\Client\ClientListScreen;
use App\Orchid\Screens\Client\ClientEditScreen;
use App\Orchid\Screens\Client\ClientPendingScreen;
use App\Orchid\Screens\Booking\BookingListScreen;
use App\Orchid\Screens\Booking\BookingProcessScreen;
use App\Orchid\Screens\Payment\PaymentListScreen;
use App\Orchid\Screens\Payment\PaymentVerifyScreen;
use App\Orchid\Screens\Locker\LockerListScreen;
use App\Orchid\Screens\Subscription\SubscriptionListScreen;
use App\Orchid\Screens\Subscription\SubscriptionEditScreen;
use App\Orchid\Screens\Setting\SettingScreen;
use Illuminate\Support\Facades\Route;
use Tabuna\Breadcrumbs\Trail;

// Dashboard
Route::screen('dashboard', DashboardScreen::class)
    ->name('platform.dashboard')
    ->breadcrumbs(fn(Trail $trail) => $trail->push('Dashboard'));

// Clients
Route::screen('clients', ClientListScreen::class)
    ->name('platform.clients')
    ->breadcrumbs(fn(Trail $trail) => $trail
        ->parent('platform.dashboard')
        ->push('Клиенты'));

Route::screen('clients/pending', ClientPendingScreen::class)
    ->name('platform.clients.pending')
    ->breadcrumbs(fn(Trail $trail) => $trail
        ->parent('platform.clients')
        ->push('Ожидают подтверждения'));

Route::screen('clients/{client}/edit', ClientEditScreen::class)
    ->name('platform.clients.edit')
    ->breadcrumbs(fn(Trail $trail, $client) => $trail
        ->parent('platform.clients')
        ->push($client->display_name));

// Bookings
Route::screen('bookings', BookingListScreen::class)
    ->name('platform.bookings')
    ->breadcrumbs(fn(Trail $trail) => $trail
        ->parent('platform.dashboard')
        ->push('Бронирования'));

Route::screen('bookings/{bookingRequest}/process', BookingProcessScreen::class)
    ->name('platform.bookings.process')
    ->breadcrumbs(fn(Trail $trail, $bookingRequest) => $trail
        ->parent('platform.bookings')
        ->push("Запрос #{$bookingRequest->id}"));

// Payments
Route::screen('payments', PaymentListScreen::class)
    ->name('platform.payments')
    ->breadcrumbs(fn(Trail $trail) => $trail
        ->parent('platform.dashboard')
        ->push('Платежи'));

Route::screen('payments/{payment}/verify', PaymentVerifyScreen::class)
    ->name('platform.payments.verify')
    ->breadcrumbs(fn(Trail $trail, $payment) => $trail
        ->parent('platform.payments')
        ->push("Платеж #{$payment->id}"));

// Lockers
Route::screen('lockers', LockerListScreen::class)
    ->name('platform.lockers')
    ->breadcrumbs(fn(Trail $trail) => $trail
        ->parent('platform.dashboard')
        ->push('Шкафы'));

// Subscriptions
Route::screen('subscriptions', SubscriptionListScreen::class)
    ->name('platform.subscriptions')
    ->breadcrumbs(fn(Trail $trail) => $trail
        ->parent('platform.dashboard')
        ->push('Подписки'));

Route::screen('subscriptions/{subscription}/edit', SubscriptionEditScreen::class)
    ->name('platform.subscriptions.edit')
    ->breadcrumbs(fn(Trail $trail, $subscription) => $trail
        ->parent('platform.subscriptions')
        ->push("Подписка #{$subscription->id}"));

// Settings
Route::screen('settings', SettingScreen::class)
    ->name('platform.settings')
    ->breadcrumbs(fn(Trail $trail) => $trail
        ->parent('platform.dashboard')
        ->push('Настройки'));
```

---

## 2. Dashboard Screen

### 2.1 DashboardScreen

**Файл:** `app/Orchid/Screens/Dashboard/DashboardScreen.php`

```php
<?php

namespace App\Orchid\Screens\Dashboard;

use App\Models\BookingRequest;
use App\Models\Client;
use App\Models\Locker;
use App\Models\Payment;
use App\Models\Subscription;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class DashboardScreen extends Screen
{
    public function name(): ?string
    {
        return 'Dashboard';
    }

    public function description(): ?string
    {
        return 'Общая статистика гольф-клуба';
    }

    public function query(): iterable
    {
        return [
            'metrics' => [
                'clients_total' => Client::approved()->count(),
                'clients_pending' => Client::pending()->count(),
                'subscriptions_active' => Subscription::active()->count(),
                'lockers_available' => Locker::available()->count(),
                'lockers_total' => Locker::count(),
                'bookings_pending' => BookingRequest::awaitingAction()->count(),
                'payments_pending' => Payment::pending()->count(),
            ],
        ];
    }

    public function commandBar(): iterable
    {
        return [];
    }

    public function layout(): iterable
    {
        return [
            Layout::metrics([
                'Активных клиентов' => 'metrics.clients_total',
                'Ожидают подтверждения' => 'metrics.clients_pending',
                'Активных подписок' => 'metrics.subscriptions_active',
                'Шкафов свободно' => 'metrics.lockers_available',
            ]),

            Layout::columns([
                Layout::view('platform.dashboard.pending-clients'),
                Layout::view('platform.dashboard.pending-bookings'),
            ]),

            Layout::columns([
                Layout::view('platform.dashboard.pending-payments'),
                Layout::view('platform.dashboard.expiring-subscriptions'),
            ]),
        ];
    }
}
```

### 2.2 Dashboard Views

**Файл:** `resources/views/platform/dashboard/pending-clients.blade.php`

```blade
<div class="bg-white rounded shadow-sm p-4 mb-3">
    <h5 class="text-muted mb-3">
        <x-orchid-icon path="bs.person-plus" class="me-2"/>
        Новые клиенты
    </h5>
    
    @php
        $pendingClients = \App\Models\Client::pending()
            ->latest()
            ->take(5)
            ->get();
    @endphp

    @forelse($pendingClients as $client)
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <div>
                <strong>{{ $client->display_name }}</strong>
                <br>
                <small class="text-muted">{{ $client->phone_number }}</small>
            </div>
            <a href="{{ route('platform.clients.edit', $client) }}" 
               class="btn btn-sm btn-outline-primary">
                Рассмотреть
            </a>
        </div>
    @empty
        <p class="text-muted mb-0">Нет новых заявок</p>
    @endforelse

    @if($pendingClients->count() > 0)
        <div class="mt-3">
            <a href="{{ route('platform.clients.pending') }}" class="btn btn-sm btn-link">
                Все заявки →
            </a>
        </div>
    @endif
</div>
```

**Файл:** `resources/views/platform/dashboard/pending-bookings.blade.php`

```blade
<div class="bg-white rounded shadow-sm p-4 mb-3">
    <h5 class="text-muted mb-3">
        <x-orchid-icon path="bs.calendar-check" class="me-2"/>
        Запросы на бронирование
    </h5>
    
    @php
        $pendingBookings = \App\Models\BookingRequest::with('client')
            ->awaitingAction()
            ->latest()
            ->take(5)
            ->get();
    @endphp

    @forelse($pendingBookings as $booking)
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <div>
                <strong>{{ $booking->client->display_name }}</strong>
                <br>
                <small class="text-muted">
                    {{ $booking->service_type->label() }} • ${{ $booking->total_price }}
                </small>
            </div>
            <a href="{{ route('platform.bookings.process', $booking) }}" 
               class="btn btn-sm btn-outline-primary">
                Обработать
            </a>
        </div>
    @empty
        <p class="text-muted mb-0">Нет новых запросов</p>
    @endforelse

    @if($pendingBookings->count() > 0)
        <div class="mt-3">
            <a href="{{ route('platform.bookings') }}" class="btn btn-sm btn-link">
                Все запросы →
            </a>
        </div>
    @endif
</div>
```

**Файл:** `resources/views/platform/dashboard/pending-payments.blade.php`

```blade
<div class="bg-white rounded shadow-sm p-4 mb-3">
    <h5 class="text-muted mb-3">
        <x-orchid-icon path="bs.credit-card" class="me-2"/>
        Чеки на проверку
    </h5>
    
    @php
        $pendingPayments = \App\Models\Payment::with('client')
            ->pending()
            ->latest()
            ->take(5)
            ->get();
    @endphp

    @forelse($pendingPayments as $payment)
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <div>
                <strong>{{ $payment->client->display_name }}</strong>
                <br>
                <small class="text-muted">${{ $payment->amount }}</small>
            </div>
            <a href="{{ route('platform.payments.verify', $payment) }}" 
               class="btn btn-sm btn-outline-primary">
                Проверить
            </a>
        </div>
    @empty
        <p class="text-muted mb-0">Нет чеков на проверку</p>
    @endforelse

    @if($pendingPayments->count() > 0)
        <div class="mt-3">
            <a href="{{ route('platform.payments') }}" class="btn btn-sm btn-link">
                Все платежи →
            </a>
        </div>
    @endif
</div>
```

**Файл:** `resources/views/platform/dashboard/expiring-subscriptions.blade.php`

```blade
<div class="bg-white rounded shadow-sm p-4 mb-3">
    <h5 class="text-muted mb-3">
        <x-orchid-icon path="bs.exclamation-triangle" class="me-2"/>
        Истекающие подписки
    </h5>
    
    @php
        $expiringSubscriptions = \App\Models\Subscription::with('client')
            ->expiring()
            ->orderBy('end_date')
            ->take(5)
            ->get();
    @endphp

    @forelse($expiringSubscriptions as $subscription)
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <div>
                <strong>{{ $subscription->client->display_name }}</strong>
                <br>
                <small class="text-muted">
                    {{ $subscription->subscription_type->label() }}
                </small>
            </div>
            <span class="badge bg-warning">
                {{ $subscription->end_date->format('d.m.Y') }}
            </span>
        </div>
    @empty
        <p class="text-muted mb-0">Нет истекающих подписок</p>
    @endforelse

    @if($expiringSubscriptions->count() > 0)
        <div class="mt-3">
            <a href="{{ route('platform.subscriptions') }}?filter[expiring]=1" 
               class="btn btn-sm btn-link">
                Все истекающие →
            </a>
        </div>
    @endif
</div>
```

---

## 3. Client Screens

### 3.1 ClientListScreen

**Файл:** `app/Orchid/Screens/Client/ClientListScreen.php`

```php
<?php

namespace App\Orchid\Screens\Client;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Orchid\Layouts\Client\ClientListLayout;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class ClientListScreen extends Screen
{
    public function name(): ?string
    {
        return 'Клиенты';
    }

    public function description(): ?string
    {
        return 'Список всех клиентов гольф-клуба';
    }

    public function query(): iterable
    {
        return [
            'clients' => Client::with('approvedBy')
                ->filters()
                ->defaultSort('created_at', 'desc')
                ->paginate(15),
        ];
    }

    public function commandBar(): iterable
    {
        return [];
    }

    public function layout(): iterable
    {
        return [
            Layout::selection([
                Layout::tabs([
                    'Все' => ClientListLayout::class,
                ]),
            ]),

            ClientListLayout::class,
        ];
    }
}
```

### 3.2 ClientListLayout

**Файл:** `app/Orchid/Layouts/Client/ClientListLayout.php`

```php
<?php

namespace App\Orchid\Layouts\Client;

use App\Enums\ClientStatus;
use App\Models\Client;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class ClientListLayout extends Table
{
    protected $target = 'clients';

    protected function columns(): iterable
    {
        return [
            TD::make('id', 'ID')
                ->sort()
                ->width('80px'),

            TD::make('display_name', 'Имя')
                ->sort('first_name')
                ->filter(Input::make())
                ->render(fn(Client $client) => Link::make($client->display_name)
                    ->route('platform.clients.edit', $client)),

            TD::make('phone_number', 'Телефон')
                ->sort()
                ->filter(Input::make()),

            TD::make('username', 'Telegram')
                ->render(fn(Client $client) => $client->telegram_link 
                    ? "<a href='{$client->telegram_link}' target='_blank'>@{$client->username}</a>"
                    : '-'),

            TD::make('status', 'Статус')
                ->sort()
                ->filter(Select::make()
                    ->options([
                        '' => 'Все',
                        'pending' => 'Ожидает',
                        'approved' => 'Подтвержден',
                        'blocked' => 'Заблокирован',
                    ]))
                ->render(fn(Client $client) => 
                    "<span class='badge bg-{$client->status->color()}'>{$client->status->label()}</span>"),

            TD::make('subscriptions_count', 'Подписок')
                ->render(fn(Client $client) => $client->activeSubscriptions()->count()),

            TD::make('created_at', 'Регистрация')
                ->sort()
                ->render(fn(Client $client) => $client->created_at->format('d.m.Y H:i')),

            TD::make('actions', '')
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(fn(Client $client) => DropDown::make()
                    ->icon('bs.three-dots-vertical')
                    ->list([
                        Link::make('Редактировать')
                            ->route('platform.clients.edit', $client)
                            ->icon('bs.pencil'),
                    ])),
        ];
    }
}
```

### 3.3 ClientEditScreen

**Файл:** `app/Orchid/Screens/Client/ClientEditScreen.php`

```php
<?php

namespace App\Orchid\Screens\Client;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Services\ClientService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class ClientEditScreen extends Screen
{
    public ?Client $client = null;

    public function query(Client $client): iterable
    {
        $client->load(['approvedBy', 'subscriptions.locker', 'bookingRequests']);

        return [
            'client' => $client,
        ];
    }

    public function name(): ?string
    {
        return $this->client->display_name;
    }

    public function description(): ?string
    {
        return "Клиент #{$this->client->id}";
    }

    public function commandBar(): iterable
    {
        $buttons = [
            Link::make('Назад')
                ->icon('bs.arrow-left')
                ->route('platform.clients'),
        ];

        if ($this->client->isPending()) {
            $buttons[] = Button::make('Подтвердить')
                ->icon('bs.check-lg')
                ->type(Color::SUCCESS)
                ->method('approve')
                ->confirm('Подтвердить регистрацию этого клиента?');

            $buttons[] = Button::make('Отклонить')
                ->icon('bs.x-lg')
                ->type(Color::DANGER)
                ->method('reject')
                ->confirm('Отклонить регистрацию этого клиента?');
        }

        if ($this->client->isApproved()) {
            $buttons[] = Button::make('Заблокировать')
                ->icon('bs.ban')
                ->type(Color::DANGER)
                ->method('block')
                ->confirm('Заблокировать этого клиента?');
        }

        if ($this->client->isBlocked()) {
            $buttons[] = Button::make('Разблокировать')
                ->icon('bs.unlock')
                ->type(Color::SUCCESS)
                ->method('unblock')
                ->confirm('Разблокировать этого клиента?');
        }

        return $buttons;
    }

    public function layout(): iterable
    {
        return [
            Layout::block(Layout::rows([
                Input::make('client.phone_number')
                    ->title('Телефон')
                    ->disabled(),

                Input::make('client.telegram_id')
                    ->title('Telegram ID')
                    ->disabled(),

                Input::make('client.username')
                    ->title('Username')
                    ->disabled(),

                Input::make('client.first_name')
                    ->title('Имя (Telegram)')
                    ->disabled(),

                Input::make('client.last_name')
                    ->title('Фамилия (Telegram)')
                    ->disabled(),

                Input::make('client.full_name')
                    ->title('Полное имя')
                    ->placeholder('Введите ФИО клиента'),

                TextArea::make('client.notes')
                    ->title('Заметки')
                    ->rows(3),
            ]))
            ->title('Данные клиента')
            ->commands(
                Button::make('Сохранить')
                    ->type(Color::PRIMARY)
                    ->icon('bs.check')
                    ->method('save')
            ),

            Layout::block(Layout::rows([
                Input::make('client.status')
                    ->title('Статус')
                    ->value($this->client->status->label())
                    ->disabled(),

                Input::make('client.created_at')
                    ->title('Дата регистрации')
                    ->value($this->client->created_at->format('d.m.Y H:i'))
                    ->disabled(),

                Input::make('approved_by')
                    ->title('Подтвердил')
                    ->value($this->client->approvedBy?->name ?? '-')
                    ->disabled(),

                Input::make('client.approved_at')
                    ->title('Дата подтверждения')
                    ->value($this->client->approved_at?->format('d.m.Y H:i') ?? '-')
                    ->disabled(),
            ]))
            ->title('Статус регистрации'),

            Layout::block(
                Layout::view('platform.clients.subscriptions', ['client' => $this->client])
            )->title('Активные подписки'),
        ];
    }

    public function save(Client $client, Request $request): void
    {
        $client->fill($request->input('client'))->save();

        Toast::info('Данные клиента сохранены');
    }

    public function approve(Client $client, NotificationService $notificationService): void
    {
        $client->approve(auth()->user());
        
        $notificationService->notifyClientApproved($client);

        Toast::success('Клиент подтвержден');
    }

    public function reject(Client $client, NotificationService $notificationService): void
    {
        $client->reject();
        
        $notificationService->notifyClientRejected($client);

        Toast::warning('Клиент отклонен');
    }

    public function block(Client $client): void
    {
        $client->update(['status' => ClientStatus::BLOCKED]);

        Toast::warning('Клиент заблокирован');
    }

    public function unblock(Client $client): void
    {
        $client->update(['status' => ClientStatus::APPROVED]);

        Toast::success('Клиент разблокирован');
    }
}
```

### 3.4 ClientPendingScreen

**Файл:** `app/Orchid/Screens/Client/ClientPendingScreen.php`

```php
<?php

namespace App\Orchid\Screens\Client;

use App\Models\Client;
use App\Orchid\Layouts\Client\ClientPendingLayout;
use Orchid\Screen\Screen;

class ClientPendingScreen extends Screen
{
    public function name(): ?string
    {
        return 'Ожидают подтверждения';
    }

    public function description(): ?string
    {
        return 'Новые клиенты, ожидающие подтверждения регистрации';
    }

    public function query(): iterable
    {
        return [
            'clients' => Client::pending()
                ->orderBy('created_at', 'asc')
                ->paginate(15),
        ];
    }

    public function commandBar(): iterable
    {
        return [];
    }

    public function layout(): iterable
    {
        return [
            ClientPendingLayout::class,
        ];
    }
}
```

---

## 4. Booking Screens

### 4.1 BookingListScreen

**Файл:** `app/Orchid/Screens/Booking/BookingListScreen.php`

```php
<?php

namespace App\Orchid\Screens\Booking;

use App\Models\BookingRequest;
use App\Orchid\Layouts\Booking\BookingListLayout;
use Orchid\Screen\Screen;

class BookingListScreen extends Screen
{
    public function name(): ?string
    {
        return 'Запросы на бронирование';
    }

    public function description(): ?string
    {
        return 'Все запросы на бронирование услуг';
    }

    public function query(): iterable
    {
        return [
            'bookings' => BookingRequest::with(['client', 'processedBy', 'payment'])
                ->filters()
                ->defaultSort('created_at', 'desc')
                ->paginate(15),
        ];
    }

    public function commandBar(): iterable
    {
        return [];
    }

    public function layout(): iterable
    {
        return [
            BookingListLayout::class,
        ];
    }
}
```

### 4.2 BookingProcessScreen

**Файл:** `app/Orchid/Screens/Booking/BookingProcessScreen.php`

```php
<?php

namespace App\Orchid\Screens\Booking;

use App\Enums\BookingStatus;
use App\Models\BookingRequest;
use App\Models\Setting;
use App\Services\BookingService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class BookingProcessScreen extends Screen
{
    public ?BookingRequest $bookingRequest = null;

    public function query(BookingRequest $bookingRequest): iterable
    {
        $bookingRequest->load(['client', 'payment', 'processedBy']);

        return [
            'booking' => $bookingRequest,
            'client' => $bookingRequest->client,
        ];
    }

    public function name(): ?string
    {
        return "Запрос #{$this->bookingRequest->id}";
    }

    public function description(): ?string
    {
        return $this->bookingRequest->status->label();
    }

    public function commandBar(): iterable
    {
        $buttons = [
            Link::make('Назад')
                ->icon('bs.arrow-left')
                ->route('platform.bookings'),
        ];

        if ($this->bookingRequest->isPending()) {
            $buttons[] = Button::make('Подтвердить без оплаты')
                ->icon('bs.check-lg')
                ->type(Color::SUCCESS)
                ->method('approveWithoutPayment')
                ->confirm('Активировать подписку без оплаты?');

            $buttons[] = Button::make('Запросить оплату')
                ->icon('bs.credit-card')
                ->type(Color::INFO)
                ->method('requirePayment')
                ->confirm('Отправить клиенту реквизиты для оплаты?');

            $buttons[] = Button::make('Отклонить')
                ->icon('bs.x-lg')
                ->type(Color::DANGER)
                ->method('reject')
                ->confirm('Отклонить запрос на бронирование?');
        }

        if ($this->bookingRequest->status === BookingStatus::PAYMENT_SENT) {
            $buttons[] = Link::make('Проверить чек')
                ->icon('bs.receipt')
                ->type(Color::PRIMARY)
                ->route('platform.payments.verify', $this->bookingRequest->payment);
        }

        return $buttons;
    }

    public function layout(): iterable
    {
        return [
            Layout::columns([
                Layout::block(Layout::rows([
                    Input::make('client.display_name')
                        ->title('Клиент')
                        ->disabled(),

                    Input::make('client.phone_number')
                        ->title('Телефон')
                        ->disabled(),

                    Input::make('booking.service_type')
                        ->title('Тип услуги')
                        ->value($this->bookingRequest->service_type->label())
                        ->disabled(),

                    Input::make('booking.game_subscription_type')
                        ->title('Тип подписки на игру')
                        ->value($this->bookingRequest->game_subscription_type?->label() ?? '-')
                        ->disabled(),

                    Input::make('booking.locker_duration_months')
                        ->title('Срок аренды шкафа')
                        ->value($this->bookingRequest->locker_duration_months 
                            ? "{$this->bookingRequest->locker_duration_months} мес." 
                            : '-')
                        ->disabled(),

                    Input::make('booking.total_price')
                        ->title('Стоимость')
                        ->value('$' . $this->bookingRequest->total_price)
                        ->disabled(),
                ]))
                ->title('Детали запроса'),

                Layout::block(Layout::rows([
                    Input::make('booking.status')
                        ->title('Статус')
                        ->value($this->bookingRequest->status->label())
                        ->disabled(),

                    Input::make('booking.created_at')
                        ->title('Дата запроса')
                        ->value($this->bookingRequest->created_at->format('d.m.Y H:i'))
                        ->disabled(),

                    Input::make('processed_by')
                        ->title('Обработал')
                        ->value($this->bookingRequest->processedBy?->name ?? '-')
                        ->disabled(),

                    Input::make('booking.processed_at')
                        ->title('Дата обработки')
                        ->value($this->bookingRequest->processed_at?->format('d.m.Y H:i') ?? '-')
                        ->disabled(),

                    TextArea::make('booking.admin_notes')
                        ->title('Заметки')
                        ->rows(3),
                ]))
                ->title('Статус обработки')
                ->commands(
                    Button::make('Сохранить заметки')
                        ->type(Color::DEFAULT)
                        ->method('saveNotes')
                ),
            ]),
        ];
    }

    public function approveWithoutPayment(
        BookingRequest $bookingRequest,
        BookingService $bookingService,
        NotificationService $notificationService
    ): void {
        $bookingService->approveWithoutPayment($bookingRequest, auth()->user());

        Toast::success('Запрос подтвержден, подписка активирована');
    }

    public function requirePayment(
        BookingRequest $bookingRequest,
        BookingService $bookingService,
        NotificationService $notificationService
    ): void {
        $bookingService->requirePayment($bookingRequest, auth()->user());

        Toast::info('Клиенту отправлены реквизиты для оплаты');
    }

    public function reject(
        BookingRequest $bookingRequest,
        Request $request,
        NotificationService $notificationService
    ): void {
        $bookingRequest->reject(
            auth()->user(),
            $request->input('booking.admin_notes')
        );

        $notificationService->notifyBookingRejected($bookingRequest);

        Toast::warning('Запрос отклонен');
    }

    public function saveNotes(BookingRequest $bookingRequest, Request $request): void
    {
        $bookingRequest->update([
            'admin_notes' => $request->input('booking.admin_notes'),
        ]);

        Toast::info('Заметки сохранены');
    }
}
```

---

## 5. Payment Screens

### 5.1 PaymentListScreen

**Файл:** `app/Orchid/Screens/Payment/PaymentListScreen.php`

```php
<?php

namespace App\Orchid\Screens\Payment;

use App\Models\Payment;
use App\Orchid\Layouts\Payment\PaymentListLayout;
use Orchid\Screen\Screen;

class PaymentListScreen extends Screen
{
    public function name(): ?string
    {
        return 'Платежи';
    }

    public function description(): ?string
    {
        return 'Все платежи и чеки от клиентов';
    }

    public function query(): iterable
    {
        return [
            'payments' => Payment::with(['client', 'bookingRequest', 'verifiedBy'])
                ->filters()
                ->defaultSort('created_at', 'desc')
                ->paginate(15),
        ];
    }

    public function commandBar(): iterable
    {
        return [];
    }

    public function layout(): iterable
    {
        return [
            PaymentListLayout::class,
        ];
    }
}
```

### 5.2 PaymentVerifyScreen

**Файл:** `app/Orchid/Screens/Payment/PaymentVerifyScreen.php`

```php
<?php

namespace App\Orchid\Screens\Payment;

use App\Models\Payment;
use App\Services\BookingService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class PaymentVerifyScreen extends Screen
{
    public ?Payment $payment = null;

    public function query(Payment $payment): iterable
    {
        $payment->load(['client', 'bookingRequest', 'verifiedBy']);

        return [
            'payment' => $payment,
        ];
    }

    public function name(): ?string
    {
        return "Платеж #{$this->payment->id}";
    }

    public function description(): ?string
    {
        return $this->payment->status->label();
    }

    public function commandBar(): iterable
    {
        $buttons = [
            Link::make('Назад')
                ->icon('bs.arrow-left')
                ->route('platform.payments'),
        ];

        if ($this->payment->isPending()) {
            $buttons[] = Button::make('Подтвердить оплату')
                ->icon('bs.check-lg')
                ->type(Color::SUCCESS)
                ->method('verify')
                ->confirm('Подтвердить оплату и активировать подписку?');

            $buttons[] = Button::make('Отклонить')
                ->icon('bs.x-lg')
                ->type(Color::DANGER)
                ->method('reject')
                ->confirm('Отклонить платеж?');
        }

        return $buttons;
    }

    public function layout(): iterable
    {
        return [
            Layout::columns([
                Layout::block(Layout::rows([
                    Input::make('payment.client.display_name')
                        ->title('Клиент')
                        ->value($this->payment->client->display_name)
                        ->disabled(),

                    Input::make('payment.amount')
                        ->title('Сумма')
                        ->value('$' . $this->payment->amount)
                        ->disabled(),

                    Input::make('payment.created_at')
                        ->title('Дата отправки чека')
                        ->value($this->payment->created_at->format('d.m.Y H:i'))
                        ->disabled(),

                    Input::make('payment.status')
                        ->title('Статус')
                        ->value($this->payment->status->label())
                        ->disabled(),
                ]))
                ->title('Информация о платеже'),

                Layout::block(
                    Layout::view('platform.payments.receipt', ['payment' => $this->payment])
                )->title('Чек'),
            ]),

            Layout::block(Layout::rows([
                TextArea::make('rejection_reason')
                    ->title('Причина отклонения (если отклоняете)')
                    ->rows(2),
            ]))->title('Действия'),
        ];
    }

    public function verify(
        Payment $payment,
        BookingService $bookingService,
        NotificationService $notificationService
    ): void {
        $bookingService->verifyPayment($payment, auth()->user());

        Toast::success('Оплата подтверждена, подписка активирована');
    }

    public function reject(
        Payment $payment,
        Request $request,
        NotificationService $notificationService
    ): void {
        $payment->reject(auth()->user(), $request->input('rejection_reason'));

        $notificationService->notifyPaymentRejected($payment);

        Toast::warning('Платеж отклонен');
    }
}
```

### 5.3 Receipt View

**Файл:** `resources/views/platform/payments/receipt.blade.php`

```blade
<div class="text-center p-4">
    @if($payment->has_receipt)
        @if(str_starts_with($payment->receipt_file_type, 'image/'))
            <img src="{{ $payment->receipt_url }}" 
                 alt="Чек" 
                 class="img-fluid rounded shadow-sm"
                 style="max-height: 400px;">
        @else
            <div class="p-4 bg-light rounded">
                <x-orchid-icon path="bs.file-pdf" class="h1 text-danger"/>
                <p class="mt-2 mb-0">{{ $payment->receipt_file_name }}</p>
            </div>
        @endif
        
        <div class="mt-3">
            <a href="{{ $payment->receipt_url }}" 
               target="_blank" 
               class="btn btn-outline-primary">
                <x-orchid-icon path="bs.download"/> Скачать файл
            </a>
        </div>
    @else
        <div class="text-muted p-4">
            <x-orchid-icon path="bs.file-earmark-x" class="h1"/>
            <p class="mt-2 mb-0">Чек не загружен</p>
        </div>
    @endif
</div>
```

---

## 6. Locker Screen

### 6.1 LockerListScreen

**Файл:** `app/Orchid/Screens/Locker/LockerListScreen.php`

```php
<?php

namespace App\Orchid\Screens\Locker;

use App\Models\Locker;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class LockerListScreen extends Screen
{
    public function name(): ?string
    {
        return 'Шкафы';
    }

    public function description(): ?string
    {
        $available = Locker::available()->count();
        $total = Locker::count();
        return "Свободно: {$available} из {$total}";
    }

    public function query(): iterable
    {
        return [
            'lockers' => Locker::with('activeSubscription.client')
                ->orderBy('locker_number')
                ->paginate(20),
        ];
    }

    public function commandBar(): iterable
    {
        return [];
    }

    public function layout(): iterable
    {
        return [
            Layout::table('lockers', [
                TD::make('locker_number', 'Номер')
                    ->width('100px')
                    ->render(fn(Locker $locker) => 
                        "<strong>#{$locker->locker_number}</strong>"),

                TD::make('status', 'Статус')
                    ->width('150px')
                    ->render(fn(Locker $locker) => 
                        "<span class='badge bg-{$locker->status->color()}'>{$locker->status->label()}</span>"),

                TD::make('client', 'Клиент')
                    ->render(function(Locker $locker) {
                        $subscription = $locker->activeSubscription;
                        if (!$subscription) return '-';
                        
                        return $subscription->client->display_name;
                    }),

                TD::make('end_date', 'Аренда до')
                    ->render(function(Locker $locker) {
                        $subscription = $locker->activeSubscription;
                        if (!$subscription) return '-';
                        
                        $endDate = $subscription->end_date;
                        $class = $subscription->is_expiring ? 'text-warning' : '';
                        
                        return "<span class='{$class}'>{$endDate->format('d.m.Y')}</span>";
                    }),

                TD::make('actions', '')
                    ->width('100px')
                    ->render(function(Locker $locker) {
                        if (!$locker->isAvailable()) {
                            return Button::make('Освободить')
                                ->type(Color::DANGER)
                                ->icon('bs.unlock')
                                ->method('release', ['locker' => $locker->id])
                                ->confirm('Освободить шкаф? Подписка будет отменена.');
                        }
                        return '';
                    }),
            ]),
        ];
    }

    public function release(int $locker): void
    {
        $locker = Locker::findOrFail($locker);
        
        if ($subscription = $locker->activeSubscription) {
            $subscription->cancel(auth()->user(), 'Шкаф освобожден администратором');
        }
        
        $locker->release();

        Toast::success("Шкаф #{$locker->locker_number} освобожден");
    }
}
```

---

## 7. Subscription Screen

### 7.1 SubscriptionListScreen

**Файл:** `app/Orchid/Screens/Subscription/SubscriptionListScreen.php`

```php
<?php

namespace App\Orchid\Screens\Subscription;

use App\Models\Subscription;
use App\Orchid\Layouts\Subscription\SubscriptionListLayout;
use Orchid\Screen\Screen;

class SubscriptionListScreen extends Screen
{
    public function name(): ?string
    {
        return 'Подписки';
    }

    public function description(): ?string
    {
        return 'Все подписки клиентов';
    }

    public function query(): iterable
    {
        return [
            'subscriptions' => Subscription::with(['client', 'locker'])
                ->filters()
                ->defaultSort('created_at', 'desc')
                ->paginate(15),
        ];
    }

    public function commandBar(): iterable
    {
        return [];
    }

    public function layout(): iterable
    {
        return [
            SubscriptionListLayout::class,
        ];
    }
}
```

---

## 8. Settings Screen

### 8.1 SettingScreen

**Файл:** `app/Orchid/Screens/Setting/SettingScreen.php`

```php
<?php

namespace App\Orchid\Screens\Setting;

use App\Models\Setting;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class SettingScreen extends Screen
{
    public function name(): ?string
    {
        return 'Настройки системы';
    }

    public function description(): ?string
    {
        return 'Управление параметрами гольф-клуба';
    }

    public function query(): iterable
    {
        return [
            'settings' => Setting::all()->pluck('value', 'key')->toArray(),
        ];
    }

    public function commandBar(): iterable
    {
        return [
            Button::make('Сохранить')
                ->icon('bs.check')
                ->type(Color::PRIMARY)
                ->method('save'),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::block(Layout::rows([
                Input::make('settings.payment_card_number')
                    ->title('Номер карты')
                    ->placeholder('0000 0000 0000 0000')
                    ->help('Номер карты для приема платежей'),

                Input::make('settings.payment_card_holder')
                    ->title('Имя владельца карты')
                    ->placeholder('IVAN IVANOV'),
            ]))->title('Платежные реквизиты'),

            Layout::block(Layout::rows([
                Input::make('settings.contact_phone')
                    ->title('Контактный телефон')
                    ->placeholder('+998 xx xxx-xx-xx')
                    ->help('Телефон для связи с администрацией'),
            ]))->title('Контакты'),

            Layout::block(Layout::rows([
                Input::make('settings.game_once_price')
                    ->title('Единоразовая игра ($)')
                    ->type('number')
                    ->step('0.01'),

                Input::make('settings.game_monthly_price')
                    ->title('Месячная подписка ($)')
                    ->type('number')
                    ->step('0.01'),

                Input::make('settings.locker_monthly_price')
                    ->title('Аренда шкафа в месяц ($)')
                    ->type('number')
                    ->step('0.01'),
            ]))->title('Тарифы'),

            Layout::block(Layout::rows([
                Input::make('settings.notification_days_before')
                    ->title('Уведомлять за N дней')
                    ->type('number')
                    ->help('За сколько дней уведомлять об истечении подписки'),

                TextArea::make('settings.welcome_message')
                    ->title('Приветственное сообщение')
                    ->rows(3)
                    ->help('Сообщение для новых клиентов'),
            ]))->title('Уведомления'),
        ];
    }

    public function save(Request $request): void
    {
        $settings = $request->input('settings', []);

        foreach ($settings as $key => $value) {
            Setting::setValue($key, $value);
        }

        Toast::success('Настройки сохранены');
    }
}
```

---

## 9. Команды для создания файлов

```bash
# Создать директории
mkdir -p app/Orchid/Screens/Dashboard
mkdir -p app/Orchid/Screens/Client
mkdir -p app/Orchid/Screens/Booking
mkdir -p app/Orchid/Screens/Payment
mkdir -p app/Orchid/Screens/Locker
mkdir -p app/Orchid/Screens/Subscription
mkdir -p app/Orchid/Screens/Setting
mkdir -p app/Orchid/Layouts/Client
mkdir -p app/Orchid/Layouts/Booking
mkdir -p app/Orchid/Layouts/Payment
mkdir -p app/Orchid/Layouts/Subscription
mkdir -p resources/views/platform/dashboard
mkdir -p resources/views/platform/clients
mkdir -p resources/views/platform/payments

# Создать экраны через Orchid команду
php artisan orchid:screen Dashboard/DashboardScreen
php artisan orchid:screen Client/ClientListScreen
php artisan orchid:screen Client/ClientEditScreen
php artisan orchid:screen Client/ClientPendingScreen
php artisan orchid:screen Booking/BookingListScreen
php artisan orchid:screen Booking/BookingProcessScreen
php artisan orchid:screen Payment/PaymentListScreen
php artisan orchid:screen Payment/PaymentVerifyScreen
php artisan orchid:screen Locker/LockerListScreen
php artisan orchid:screen Subscription/SubscriptionListScreen
php artisan orchid:screen Setting/SettingScreen

# Создать layouts
php artisan orchid:table Client/ClientListLayout
php artisan orchid:table Client/ClientPendingLayout
php artisan orchid:table Booking/BookingListLayout
php artisan orchid:table Payment/PaymentListLayout
php artisan orchid:table Subscription/SubscriptionListLayout
```

---

## 10. Критерии завершения этапа

- [ ] Dashboard показывает актуальную статистику
- [ ] Список клиентов работает с фильтрацией и сортировкой
- [ ] Можно подтверждать/отклонять новых клиентов
- [ ] Список бронирований отображается корректно
- [ ] Можно обрабатывать запросы (подтвердить/требовать оплату/отклонить)
- [ ] Чеки отображаются и можно их проверять
- [ ] Шкафы отображаются с правильными статусами
- [ ] Подписки отображаются с фильтрацией
- [ ] Настройки сохраняются и применяются
- [ ] Меню навигации работает корректно
- [ ] Badge счетчики обновляются
