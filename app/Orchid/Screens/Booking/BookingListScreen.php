<?php

namespace App\Orchid\Screens\Booking;

use App\Helpers\PaymentMode;
use App\Models\BookingRequest;
use App\Orchid\Filters\BookingStatusFilter;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;

class BookingListScreen extends Screen
{
    public function name(): ?string
    {
        return 'Все бронирования';
    }

    public function query(): iterable
    {
        return [
            'bookings' => BookingRequest::with('client')
                ->filters()
                ->defaultSort('created_at', 'desc')
                ->paginate(15),
        ];
    }

    public function commandBar(): iterable
    {
        return [
            Link::make('📥 Экспорт Excel')
                ->icon('bs.download')
                ->href(route('platform.export.bookings'))
                ->type(Color::SUCCESS),
        ];
    }

    public function layout(): iterable
    {
        $columns = [
            TD::make('id', 'ID')->sort(),

            TD::make('client.display_name', 'Клиент')
                ->render(fn (BookingRequest $booking) => Link::make($booking->client->display_name)
                    ->route('platform.clients.edit', $booking->client)),

            TD::make('service_type', 'Услуга')
                ->render(fn (BookingRequest $booking) => $booking->service_type->label()),
        ];

        if (PaymentMode::isWithPayment()) {
            $columns[] = TD::make('total_price', 'Сумма')
                ->sort()
                ->render(fn (BookingRequest $booking) => '$' . number_format($booking->total_price, 2));
        }

        $columns[] = TD::make('status', 'Статус')
            ->render(fn (BookingRequest $booking) =>
                "<span class='badge bg-{$booking->status->color()}'>{$booking->status->label()}</span>");

        $columns[] = TD::make('created_at', 'Дата')
            ->sort()
            ->render(fn (BookingRequest $booking) => $booking->created_at->format('d.m.Y H:i'));

        $columns[] = TD::make('action', '')
            ->render(fn (BookingRequest $booking) => Link::make('Открыть')
                ->class('btn btn-sm btn-outline-primary')
                ->route('platform.bookings.edit', $booking));

        return [
            Layout::selection([BookingStatusFilter::class]),
            Layout::table('bookings', $columns),
        ];
    }
}
