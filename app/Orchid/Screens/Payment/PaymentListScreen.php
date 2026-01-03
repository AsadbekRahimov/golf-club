<?php

namespace App\Orchid\Screens\Payment;

use App\Models\Payment;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;

class PaymentListScreen extends Screen
{
    public function name(): ?string
    {
        return 'Все платежи';
    }

    public function query(): iterable
    {
        return [
            'payments' => Payment::with(['client', 'bookingRequest'])
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
                ->href(route('platform.export.payments'))
                ->type(Color::SUCCESS),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::table('payments', [
                TD::make('id', 'ID')->sort(),

                TD::make('client.display_name', 'Клиент'),

                TD::make('amount', 'Сумма')
                    ->sort()
                    ->render(fn (Payment $p) => '$' . number_format($p->amount, 2)),

                TD::make('has_receipt', 'Чек')
                    ->render(fn (Payment $p) => $p->has_receipt ? '✅' : '❌'),

                TD::make('status', 'Статус')
                    ->render(fn (Payment $p) => 
                        "<span class='badge bg-{$p->status->color()}'>{$p->status->label()}</span>"),

                TD::make('created_at', 'Дата')
                    ->sort()
                    ->render(fn (Payment $p) => $p->created_at->format('d.m.Y H:i')),

                TD::make('action', '')
                    ->render(fn (Payment $p) => Link::make('Открыть')
                        ->class('btn btn-sm btn-outline-primary')
                        ->route('platform.bookings.edit', $p->bookingRequest)),
            ]),
        ];
    }
}
