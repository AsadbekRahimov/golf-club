<?php

namespace App\Services;

use App\Models\BookingRequest;
use App\Models\Client;
use App\Models\Locker;
use App\Models\Subscription;
use Carbon\Carbon;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    public function exportClients(?string $status = null): StreamedResponse
    {
        $query = Client::query()->with('approvedBy');

        if ($status) {
            $query->where('status', $status);
        }

        $clients = $query->orderBy('created_at', 'desc')->get();

        return (new FastExcel($clients))->download('clients_' . now()->format('Y-m-d_H-i') . '.xlsx', function ($client) {
            return [
                'ID' => $client->id,
                'Телефон' => $client->phone_number,
                'Имя' => $client->first_name,
                'Фамилия' => $client->last_name,
                'Username' => $client->username,
                'Статус' => $client->status->label(),
                'Telegram ID' => $client->telegram_id,
                'Дата регистрации' => $client->created_at->format('d.m.Y H:i'),
                'Дата подтверждения' => $client->approved_at?->format('d.m.Y H:i') ?? '-',
                'Подтвердил' => $client->approvedBy?->name ?? '-',
                'Заметки' => $client->notes ?? '-',
            ];
        });
    }

    public function exportBookings(?string $status = null): StreamedResponse
    {
        $query = BookingRequest::query()->with(['client', 'processedBy']);

        if ($status) {
            $query->where('status', $status);
        }

        $bookings = $query->orderBy('created_at', 'desc')->get();

        return (new FastExcel($bookings))->download('bookings_' . now()->format('Y-m-d_H-i') . '.xlsx', function ($booking) {
            return [
                'ID' => $booking->id,
                'Клиент' => $booking->client->display_name ?? '-',
                'Телефон' => $booking->client->phone_number ?? '-',
                'Тип услуги' => $booking->service_type->label(),
                'Тип игры' => $booking->game_subscription_type?->label() ?? '-',
                'Срок шкафа (мес)' => $booking->locker_duration_months ?? '-',
                'Статус' => $booking->status->label(),
                'Дата создания' => $booking->created_at->format('d.m.Y H:i'),
                'Обработал' => $booking->processedBy?->name ?? '-',
                'Дата обработки' => $booking->processed_at?->format('d.m.Y H:i') ?? '-',
                'Заметки' => $booking->admin_notes ?? '-',
            ];
        });
    }

    public function exportSubscriptions(?string $status = null): StreamedResponse
    {
        $query = Subscription::query()->with(['client', 'locker', 'bookingRequest']);

        if ($status) {
            $query->where('status', $status);
        }

        $subscriptions = $query->orderBy('created_at', 'desc')->get();

        return (new FastExcel($subscriptions))->download('subscriptions_' . now()->format('Y-m-d_H-i') . '.xlsx', function ($sub) {
            return [
                'ID' => $sub->id,
                'Клиент' => $sub->client->display_name ?? '-',
                'Телефон' => $sub->client->phone_number ?? '-',
                'Тип подписки' => $sub->subscription_type->label(),
                'Шкаф #' => $sub->locker?->locker_number ?? '-',
                'Статус' => $sub->status->label(),
                'Дата начала' => $sub->start_date->format('d.m.Y'),
                'Дата окончания' => $sub->end_date?->format('d.m.Y') ?? 'Бессрочно',
                'Осталось дней' => $sub->days_remaining ?? '-',
                'Заявка #' => $sub->booking_request_id ?? '-',
            ];
        });
    }

    public function exportLockers(): StreamedResponse
    {
        $lockers = Locker::query()
            ->with(['currentSubscription.client'])
            ->orderBy('locker_number')
            ->get();

        return (new FastExcel($lockers))->download('lockers_' . now()->format('Y-m-d_H-i') . '.xlsx', function ($locker) {
            return [
                'Номер шкафа' => $locker->locker_number,
                'Статус' => $locker->status->label(),
                'Клиент' => $locker->currentSubscription?->client?->display_name ?? '-',
                'Телефон клиента' => $locker->currentSubscription?->client?->phone_number ?? '-',
                'Аренда до' => $locker->currentSubscription?->end_date?->format('d.m.Y') ?? '-',
            ];
        });
    }

    public function exportAllData(): StreamedResponse
    {
        $data = collect();

        $data->push(['=== КЛИЕНТЫ ===']);
        $data->push(['ID', 'Телефон', 'Имя', 'Статус', 'Дата регистрации']);

        Client::orderBy('created_at', 'desc')->each(function ($client) use ($data) {
            $data->push([
                $client->id,
                $client->phone_number,
                $client->display_name,
                $client->status->label(),
                $client->created_at->format('d.m.Y H:i'),
            ]);
        });

        $data->push(['']);
        $data->push(['=== БРОНИРОВАНИЯ ===']);
        $data->push(['ID', 'Клиент', 'Услуга', 'Статус', 'Дата']);

        BookingRequest::with('client')->orderBy('created_at', 'desc')->each(function ($booking) use ($data) {
            $data->push([
                $booking->id,
                $booking->client->display_name ?? '-',
                $booking->service_type->label(),
                $booking->status->label(),
                $booking->created_at->format('d.m.Y H:i'),
            ]);
        });

        $data->push(['']);
        $data->push(['=== ПОДПИСКИ ===']);
        $data->push(['ID', 'Клиент', 'Тип', 'Шкаф', 'Статус', 'До']);

        Subscription::with(['client', 'locker'])->orderBy('created_at', 'desc')->each(function ($sub) use ($data) {
            $data->push([
                $sub->id,
                $sub->client->display_name ?? '-',
                $sub->subscription_type->label(),
                $sub->locker?->locker_number ?? '-',
                $sub->status->label(),
                $sub->end_date?->format('d.m.Y') ?? 'Бессрочно',
            ]);
        });

        return (new FastExcel($data))->download('golf_club_export_' . now()->format('Y-m-d_H-i') . '.xlsx');
    }

    public function exportDashboardReport(Carbon $startDate, Carbon $endDate): StreamedResponse
    {
        $data = collect();

        $data->push(['ОТЧЁТ GOLF CLUB']);
        $data->push(['Период: ' . $startDate->format('d.m.Y') . ' - ' . $endDate->format('d.m.Y')]);
        $data->push(['Сформирован: ' . now()->format('d.m.Y H:i')]);
        $data->push(['']);

        $data->push(['=== СВОДКА ===']);
        $data->push(['Показатель', 'Значение']);
        $data->push(['Всего клиентов', Client::approved()->count()]);
        $data->push(['Новых клиентов за период', Client::whereBetween('created_at', [$startDate, $endDate])->count()]);
        $data->push(['Активных подписок', Subscription::active()->count()]);
        $data->push(['Бронирований за период', BookingRequest::whereBetween('created_at', [$startDate, $endDate])->count()]);
        $data->push(['Свободных шкафов', Locker::available()->count()]);
        $data->push(['Занятых шкафов', Locker::occupied()->count()]);
        $data->push(['']);

        $data->push(['=== КЛИЕНТЫ ЗА ПЕРИОД ===']);
        $data->push(['ID', 'Телефон', 'Имя', 'Статус', 'Дата регистрации']);

        Client::whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->each(function ($client) use ($data) {
                $data->push([
                    $client->id,
                    $client->phone_number,
                    $client->display_name,
                    $client->status->label(),
                    $client->created_at->format('d.m.Y H:i'),
                ]);
            });

        $data->push(['']);
        $data->push(['=== БРОНИРОВАНИЯ ЗА ПЕРИОД ===']);
        $data->push(['ID', 'Клиент', 'Услуга', 'Статус', 'Дата']);

        BookingRequest::with('client')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->each(function ($booking) use ($data) {
                $data->push([
                    $booking->id,
                    $booking->client->display_name ?? '-',
                    $booking->service_type->label(),
                    $booking->status->label(),
                    $booking->created_at->format('d.m.Y H:i'),
                ]);
            });

        return (new FastExcel($data))->download('golf_club_report_' . now()->format('Y-m-d_H-i') . '.xlsx');
    }
}
