<?php

namespace App\Orchid\Screens\Locker;

use App\Enums\LockerStatus;
use App\Models\Locker;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Fields\Input;
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
                ->filters()
                ->defaultSort('locker_number')
                ->paginate(20),
        ];
    }

    public function commandBar(): iterable
    {
        return [
            Link::make('📥 Экспорт Excel')
                ->icon('bs.download')
                ->href(route('platform.export.lockers'))
                ->type(Color::SUCCESS),

            Button::make('Добавить шкафы')
                ->icon('bs.plus')
                ->type(Color::PRIMARY)
                ->method('addLockers'),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::rows([
                Input::make('count')
                    ->type('number')
                    ->title('Количество новых шкафов')
                    ->value(10)
                    ->help('Шкафы будут созданы с автоматической нумерацией'),
            ])->title('Добавить шкафы'),

            Layout::table('lockers', [
                TD::make('locker_number', 'Номер')
                    ->sort()
                    ->render(fn (Locker $l) => "#{$l->locker_number}"),

                TD::make('status', 'Статус')
                    ->render(fn (Locker $l) => 
                        "<span class='badge bg-{$l->status->color()}'>{$l->status->label()}</span>"),

                TD::make('client', 'Клиент')
                    ->render(fn (Locker $l) => $l->activeSubscription?->client?->display_name ?? '-'),

                TD::make('end_date', 'Аренда до')
                    ->render(fn (Locker $l) => $l->activeSubscription?->end_date?->format('d.m.Y') ?? '-'),

                TD::make('days_remaining', 'Осталось дней')
                    ->render(function (Locker $l) {
                        $days = $l->activeSubscription?->days_remaining;
                        if ($days === null) return '-';
                        $color = $days <= 3 ? 'danger' : ($days <= 7 ? 'warning' : 'success');
                        return "<span class='badge bg-{$color}'>{$days}</span>";
                    }),

                TD::make('description', 'Описание'),

                TD::make('action', '')
                    ->render(fn (Locker $l) => $l->status === LockerStatus::OCCUPIED
                        ? Button::make('Освободить')
                            ->class('btn btn-sm btn-outline-danger')
                            ->method('release', ['locker' => $l->id])
                            ->confirm('Вы уверены? Связанная подписка будет отменена.')
                        : ''),
            ]),
        ];
    }

    public function release(Request $request): void
    {
        $locker = Locker::findOrFail($request->input('locker'));

        $activeSubscription = $locker->activeSubscription;
        if ($activeSubscription) {
            $activeSubscription->cancel(auth()->user(), 'Шкаф освобождён вручную администратором');
        }

        $locker->release();

        Toast::warning("Шкаф #{$locker->locker_number} освобождён");
    }

    public function addLockers(Request $request): void
    {
        $count = (int) $request->input('count', 10);
        
        $lastNumber = Locker::max('locker_number') ?? '000';
        $startNumber = (int) $lastNumber + 1;

        for ($i = 0; $i < $count; $i++) {
            Locker::create([
                'locker_number' => str_pad($startNumber + $i, 3, '0', STR_PAD_LEFT),
                'status' => LockerStatus::AVAILABLE,
            ]);
        }

        Toast::success("Добавлено {$count} шкафов");
    }
}
