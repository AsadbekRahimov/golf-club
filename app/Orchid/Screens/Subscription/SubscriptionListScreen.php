<?php

namespace App\Orchid\Screens\Subscription;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class SubscriptionListScreen extends Screen
{
    public function name(): ?string
    {
        return 'Подписки';
    }

    public function description(): ?string
    {
        $active = Subscription::active()->count();
        $expiring = Subscription::expiring()->count();
        return "Активных: {$active}, Истекает скоро: {$expiring}";
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
        return [
            Link::make('📥 Экспорт Excel')
                ->icon('bs.download')
                ->href(route('platform.export.subscriptions'))
                ->type(Color::SUCCESS),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::table('subscriptions', [
                TD::make('id', 'ID')->sort(),

                TD::make('client.display_name', 'Клиент')
                    ->render(fn (Subscription $s) => Link::make($s->client->display_name)
                        ->route('platform.clients.edit', $s->client)),

                TD::make('subscription_type', 'Тип')
                    ->render(fn (Subscription $s) => $s->subscription_type->label()),

                TD::make('locker.locker_number', 'Шкаф')
                    ->render(fn (Subscription $s) => $s->locker ? "#{$s->locker->locker_number}" : '-'),

                TD::make('start_date', 'Начало')
                    ->sort()
                    ->render(fn (Subscription $s) => $s->start_date->format('d.m.Y')),

                TD::make('end_date', 'Окончание')
                    ->sort()
                    ->render(function (Subscription $s) {
                        if (!$s->end_date) return 'Бессрочно';
                        $text = $s->end_date->format('d.m.Y');
                        if ($s->is_expiring) {
                            $text .= " <span class='badge bg-warning'>Истекает</span>";
                        }
                        return $text;
                    }),

                TD::make('price', 'Цена')
                    ->render(fn (Subscription $s) => '$' . number_format($s->price, 2)),

                TD::make('status', 'Статус')
                    ->render(fn (Subscription $s) => 
                        "<span class='badge bg-{$s->status->color()}'>{$s->status->label()}</span>"),

                TD::make('action', '')
                    ->render(fn (Subscription $s) => $s->isActive() 
                        ? Button::make('Отменить')
                            ->class('btn btn-sm btn-outline-danger')
                            ->method('cancel', ['subscription' => $s->id])
                        : ''),
            ]),
        ];
    }

    public function cancel(Request $request): void
    {
        $subscription = Subscription::findOrFail($request->input('subscription'));
        $subscription->cancel(auth()->user(), 'Отменено администратором');

        Toast::warning('Подписка отменена');
    }
}
