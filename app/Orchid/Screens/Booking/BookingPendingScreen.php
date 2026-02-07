<?php

namespace App\Orchid\Screens\Booking;

use App\Models\BookingRequest;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;

class BookingPendingScreen extends Screen
{
    public function name(): ?string
    {
        return 'Ожидающие бронирования';
    }

    public function description(): ?string
    {
        return 'Запросы, требующие рассмотрения';
    }

    public function query(): iterable
    {
        return [
            'bookings' => BookingRequest::with('client')
                ->awaitingAction()
                ->orderBy('created_at', 'desc')
                ->paginate(15),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::table('bookings', [
                TD::make('client.display_name', 'Клиент')
                    ->render(fn (BookingRequest $booking) => $booking->client->display_name),

                TD::make('client.phone_number', 'Телефон'),

                TD::make('service_type', 'Услуга')
                    ->render(fn (BookingRequest $booking) => $booking->service_type->label()),

                TD::make('total_price', 'Сумма')
                    ->render(fn (BookingRequest $booking) => '$' . number_format($booking->total_price, 2)),

                TD::make('preferred_date', 'Дата визита')
                    ->render(fn (BookingRequest $booking) => $booking->preferred_date
                        ? $booking->preferred_date->format('d.m.Y') . ($booking->preferred_time ? " {$booking->preferred_time}" : '')
                        : '-'),

                TD::make('status', 'Статус')
                    ->render(fn (BookingRequest $booking) =>
                        "<span class='badge bg-{$booking->status->color()}'>{$booking->status->label()}</span>"),

                TD::make('created_at', 'Дата')
                    ->render(fn (BookingRequest $booking) => $booking->created_at->format('d.m.Y H:i')),

                TD::make('action', '')
                    ->render(fn (BookingRequest $booking) => Link::make('Обработать')
                        ->class('btn btn-sm btn-primary')
                        ->route('platform.bookings.edit', $booking)),
            ]),
        ];
    }
}
