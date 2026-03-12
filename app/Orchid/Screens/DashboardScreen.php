<?php

namespace App\Orchid\Screens;

use App\Enums\BookingStatus;
use App\Enums\SubscriptionType;
use App\Models\BookingRequest;
use App\Models\Client;
use App\Models\Locker;
use App\Models\Subscription;
use App\Orchid\Layouts\Charts\RegistrationsChart;
use App\Orchid\Layouts\Charts\BookingsChart;
use App\Orchid\Layouts\Charts\SubscriptionsChart;
use App\Orchid\Layouts\Charts\LockersChart;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Screen\TD;
use Orchid\Support\Color;

class DashboardScreen extends Screen
{
    public ?string $period = 'month';

    public function name(): ?string
    {
        return '📊 Аналитическая панель';
    }

    public function description(): ?string
    {
        return 'Полная статистика Golf Club';
    }

    public function query(Request $request): iterable
    {
        $this->period = $request->get('period', 'month');

        $dateRange = $this->getDateRange();
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];

        $previousRange = $this->getPreviousDateRange();
        $prevStart = $previousRange['start'];
        $prevEnd = $previousRange['end'];

        return [
            'period' => $this->period,
            'period_label' => $this->getPeriodLabel(),
            'date_range' => $startDate->format('d.m.Y') . ' - ' . $endDate->format('d.m.Y'),

            'metrics' => $this->getMetrics($startDate, $endDate, $prevStart, $prevEnd),

            'registrations' => $this->getRegistrationsData($startDate, $endDate),
            'bookings' => $this->getBookingsData($startDate, $endDate),
            'subscriptions_by_type' => $this->getSubscriptionsByType(),
            'lockers_status' => $this->getLockersStatus(),

            'pending_clients' => Client::pending()->latest()->limit(5)->get(),
            'pending_bookings' => BookingRequest::with('client')->awaitingAction()->latest()->limit(5)->get(),
            'recent_subscriptions' => Subscription::with(['client', 'locker'])->active()->latest()->limit(5)->get(),
            'expiring_subscriptions' => Subscription::with(['client', 'locker'])->expiring()->limit(5)->get(),

            'top_stats' => $this->getTopStats($startDate, $endDate),
        ];
    }

    public function commandBar(): iterable
    {
        return [
            Button::make('Сегодня')
                ->method('filter', ['period' => 'today'])
                ->type($this->period === 'today' ? Color::PRIMARY : Color::SECONDARY),

            Button::make('Неделя')
                ->method('filter', ['period' => 'week'])
                ->type($this->period === 'week' ? Color::PRIMARY : Color::SECONDARY),

            Button::make('Месяц')
                ->method('filter', ['period' => 'month'])
                ->type($this->period === 'month' ? Color::PRIMARY : Color::SECONDARY),

            Button::make('Квартал')
                ->method('filter', ['period' => 'quarter'])
                ->type($this->period === 'quarter' ? Color::PRIMARY : Color::SECONDARY),

            Button::make('Год')
                ->method('filter', ['period' => 'year'])
                ->type($this->period === 'year' ? Color::PRIMARY : Color::SECONDARY),

            Button::make('Всё время')
                ->method('filter', ['period' => 'all'])
                ->type($this->period === 'all' ? Color::PRIMARY : Color::SECONDARY),

            Link::make('📥 Отчёт Excel')
                ->icon('bs.download')
                ->href(route('platform.export.report', ['period' => $this->period]))
                ->type(Color::SUCCESS),

            Link::make('📥 Все данные')
                ->icon('bs.file-earmark-spreadsheet')
                ->href(route('platform.export.all'))
                ->type(Color::INFO),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::metrics([
                '👥 Новых клиентов' => 'metrics.new_clients',
                '📝 Бронирований' => 'metrics.new_bookings',
                '📋 Активных подписок' => 'metrics.active_subscriptions',
                '🗄️ Свободных шкафов' => 'metrics.available_lockers',
                '⏳ Ожидают действий' => 'metrics.pending_actions',
            ]),

            Layout::columns([
                RegistrationsChart::class,
                BookingsChart::class,
            ]),

            Layout::columns([
                SubscriptionsChart::class,
                LockersChart::class,
            ]),

            Layout::rows([
                \Orchid\Screen\Fields\Label::make('stats_info')
                    ->title('📊 Ключевые показатели за период')
                    ->value(''),
            ]),

            Layout::metrics([
                '👥 Всего клиентов' => 'top_stats.total_clients',
                '📈 Конверсия' => 'top_stats.conversion_rate',
            ]),

            Layout::columns([
                Layout::table('pending_clients', [
                    TD::make('display_name', 'Клиент'),
                    TD::make('phone_number', 'Телефон'),
                    TD::make('created_at', 'Дата')
                        ->render(fn ($c) => $c->created_at->format('d.m.Y H:i')),
                ])->title('🆕 Новые заявки на регистрацию'),

                Layout::table('pending_bookings', [
                    TD::make('client.display_name', 'Клиент'),
                    TD::make('service_type', 'Услуга')
                        ->render(fn ($b) => $b->service_type->label()),
                ])->title('⏳ Ожидающие бронирования'),
            ]),

            Layout::table('expiring_subscriptions', [
                TD::make('client.display_name', 'Клиент'),
                TD::make('subscription_type', 'Тип')
                    ->render(fn ($s) => $s->subscription_type->label()),
                TD::make('locker.locker_number', 'Шкаф')
                    ->render(fn ($s) => $s->locker ? "#{$s->locker->locker_number}" : '-'),
                TD::make('end_date', 'Истекает')
                    ->render(fn ($s) => $s->end_date?->format('d.m.Y') ?? '-'),
                TD::make('days_remaining', 'Осталось')
                    ->render(fn ($s) => $s->days_remaining ? "{$s->days_remaining} дн." : '-'),
            ])->title('⚠️ Истекающие подписки'),

            Layout::table('recent_subscriptions', [
                TD::make('client.display_name', 'Клиент'),
                TD::make('subscription_type', 'Тип')
                    ->render(fn ($s) => $s->subscription_type->label()),
                TD::make('locker.locker_number', 'Шкаф')
                    ->render(fn ($s) => $s->locker ? "#{$s->locker->locker_number}" : '-'),
                TD::make('start_date', 'Начало')
                    ->render(fn ($s) => $s->start_date->format('d.m.Y')),
                TD::make('end_date', 'Окончание')
                    ->render(fn ($s) => $s->end_date?->format('d.m.Y') ?? 'Бессрочно'),
            ])->title('📋 Последние активные подписки'),
        ];
    }

    public function filter(Request $request): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('platform.main', [
            'period' => $request->get('period', 'month'),
        ]);
    }

    protected function getDateRange(): array
    {
        $now = Carbon::now();

        return match ($this->period) {
            'today' => ['start' => $now->copy()->startOfDay(), 'end' => $now->copy()->endOfDay()],
            'week' => ['start' => $now->copy()->startOfWeek(), 'end' => $now->copy()->endOfWeek()],
            'month' => ['start' => $now->copy()->startOfMonth(), 'end' => $now->copy()->endOfMonth()],
            'quarter' => ['start' => $now->copy()->startOfQuarter(), 'end' => $now->copy()->endOfQuarter()],
            'year' => ['start' => $now->copy()->startOfYear(), 'end' => $now->copy()->endOfYear()],
            'all' => ['start' => Carbon::create(2020, 1, 1), 'end' => $now->copy()->endOfDay()],
            default => ['start' => $now->copy()->startOfMonth(), 'end' => $now->copy()->endOfMonth()],
        };
    }

    protected function getPreviousDateRange(): array
    {
        $now = Carbon::now();

        return match ($this->period) {
            'today' => ['start' => $now->copy()->subDay()->startOfDay(), 'end' => $now->copy()->subDay()->endOfDay()],
            'week' => ['start' => $now->copy()->subWeek()->startOfWeek(), 'end' => $now->copy()->subWeek()->endOfWeek()],
            'month' => ['start' => $now->copy()->subMonth()->startOfMonth(), 'end' => $now->copy()->subMonth()->endOfMonth()],
            'quarter' => ['start' => $now->copy()->subQuarter()->startOfQuarter(), 'end' => $now->copy()->subQuarter()->endOfQuarter()],
            'year' => ['start' => $now->copy()->subYear()->startOfYear(), 'end' => $now->copy()->subYear()->endOfYear()],
            'all' => ['start' => Carbon::create(2020, 1, 1), 'end' => Carbon::create(2020, 1, 1)],
            default => ['start' => $now->copy()->subMonth()->startOfMonth(), 'end' => $now->copy()->subMonth()->endOfMonth()],
        };
    }

    protected function getPeriodLabel(): string
    {
        return match ($this->period) {
            'today' => 'Сегодня',
            'week' => 'Эта неделя',
            'month' => 'Этот месяц',
            'quarter' => 'Этот квартал',
            'year' => 'Этот год',
            'all' => 'Всё время',
            default => 'Этот месяц',
        };
    }

    protected function getMetrics(Carbon $start, Carbon $end, Carbon $prevStart, Carbon $prevEnd): array
    {
        $newClients = Client::whereBetween('created_at', [$start, $end])->count();
        $prevClients = Client::whereBetween('created_at', [$prevStart, $prevEnd])->count();

        $newBookings = BookingRequest::whereBetween('created_at', [$start, $end])->count();
        $prevBookings = BookingRequest::whereBetween('created_at', [$prevStart, $prevEnd])->count();

        $pendingClients = Client::pending()->count();
        $pendingBookings = BookingRequest::awaitingAction()->count();

        return [
            'new_clients' => $this->formatMetricWithTrend($newClients, $prevClients),
            'new_bookings' => $this->formatMetricWithTrend($newBookings, $prevBookings),
            'active_subscriptions' => number_format(Subscription::active()->count()),
            'available_lockers' => Locker::available()->count() . ' / ' . Locker::count(),
            'pending_actions' => $pendingClients + $pendingBookings,
        ];
    }

    protected function formatMetricWithTrend($value, $prevValueOrPercent, bool $isPercent = false): string
    {
        if ($isPercent) {
            $percent = $prevValueOrPercent;
        } else {
            $percent = $prevValueOrPercent > 0 ? (($value - $prevValueOrPercent) / $prevValueOrPercent * 100) : 0;
        }

        $trend = '';
        if ($percent > 0) {
            $trend = " ↑" . number_format(abs($percent), 0) . "%";
        } elseif ($percent < 0) {
            $trend = " ↓" . number_format(abs($percent), 0) . "%";
        }

        return is_numeric($value) ? number_format($value) . $trend : $value . $trend;
    }

    protected function toChartDataset(array $data, string $name): array
    {
        return [
            [
                'labels' => array_keys($data),
                'name'   => $name,
                'values' => array_values($data),
            ]
        ];
    }

    protected function getRegistrationsData(Carbon $start, Carbon $end): array
    {
        return $this->getTimeSeriesData($start, $end, 'Регистрации', fn ($s, $e) => Client::whereBetween('created_at', [$s, $e])->count());
    }

    protected function getBookingsData(Carbon $start, Carbon $end): array
    {
        return $this->getTimeSeriesData($start, $end, 'Бронирования', fn ($s, $e) => BookingRequest::whereBetween('created_at', [$s, $e])->count());
    }

    protected function getTimeSeriesData(Carbon $start, Carbon $end, string $name, \Closure $counter): array
    {
        $days = $start->diffInDays($end);

        if ($days <= 1) {
            $data = [];
            for ($hour = 0; $hour < 24; $hour += 2) {
                $hourStart = $start->copy()->setHour($hour);
                $hourEnd = $start->copy()->setHour($hour + 2);
                $data[sprintf('%02d:00', $hour)] = $counter($hourStart, $hourEnd);
            }
            return $this->toChartDataset($data, $name);
        }

        $data = [];
        $current = $start->copy();
        $interval = $days > 60 ? 'month' : ($days > 14 ? 'week' : 'day');

        while ($current <= $end) {
            $periodEnd = match ($interval) {
                'month' => $current->copy()->endOfMonth(),
                'week' => $current->copy()->endOfWeek(),
                default => $current->copy()->endOfDay(),
            };

            $label = match ($interval) {
                'month' => $current->format('M'),
                'week' => 'Нед ' . $current->weekOfYear,
                default => $current->format('d.m'),
            };

            $data[$label] = $counter($current, min($periodEnd, $end));

            $current = match ($interval) {
                'month' => $current->addMonth()->startOfMonth(),
                'week' => $current->addWeek()->startOfWeek(),
                default => $current->addDay()->startOfDay(),
            };
        }

        return $this->toChartDataset($data, $name);
    }

    protected function getSubscriptionsByType(): array
    {
        $data = [
            'Аренда шкафа' => Subscription::active()->where('subscription_type', SubscriptionType::LOCKER)->count(),
            'Тренировка' => Subscription::active()->where('subscription_type', SubscriptionType::TRAINING)->count(),
        ];

        return $this->toChartDataset($data, 'Подписки');
    }

    protected function getLockersStatus(): array
    {
        $data = [
            'Свободные' => Locker::available()->count(),
            'Занятые' => Locker::occupied()->count(),
        ];

        return $this->toChartDataset($data, 'Шкафы');
    }

    protected function getTopStats(Carbon $start, Carbon $end): array
    {
        $totalClients = Client::approved()->count();

        $registeredClients = Client::whereBetween('created_at', [$start, $end])->count();
        $convertedClients = Client::approved()->whereBetween('created_at', [$start, $end])->count();
        $conversionRate = $registeredClients > 0 ? ($convertedClients / $registeredClients * 100) : 0;

        return [
            'total_clients' => number_format($totalClients),
            'conversion_rate' => number_format($conversionRate, 1) . '%',
        ];
    }
}
