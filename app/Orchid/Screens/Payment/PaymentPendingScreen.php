<?php

namespace App\Orchid\Screens\Payment;

use App\Models\Payment;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;

class PaymentPendingScreen extends Screen
{
    public function name(): ?string
    {
        return 'Проверка чеков';
    }

    public function description(): ?string
    {
        return 'Платежи, ожидающие подтверждения';
    }

    public function query(): iterable
    {
        return [
            'payments' => Payment::with(['client', 'bookingRequest'])
                ->pending()
                ->orderBy('created_at', 'desc')
                ->paginate(15),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::table('payments', [
                TD::make('client.display_name', 'Клиент'),
                TD::make('client.phone_number', 'Телефон'),

                TD::make('amount', 'Сумма')
                    ->render(fn (Payment $p) => '$' . number_format($p->amount, 2)),

                TD::make('has_receipt', 'Чек')
                    ->render(fn (Payment $p) => $p->has_receipt 
                        ? "<a href='{$p->receipt_url}' target='_blank'>✅ Просмотр</a>" 
                        : '❌ Нет'),

                TD::make('created_at', 'Дата')
                    ->render(fn (Payment $p) => $p->created_at->format('d.m.Y H:i')),

                TD::make('action', '')
                    ->render(fn (Payment $p) => Link::make('Проверить')
                        ->class('btn btn-sm btn-primary')
                        ->route('platform.bookings.edit', $p->bookingRequest)),
            ]),
        ];
    }
}
