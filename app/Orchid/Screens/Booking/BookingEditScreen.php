<?php

namespace App\Orchid\Screens\Booking;

use App\Enums\BookingStatus;
use App\Models\BookingRequest;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Screen\Sight;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class BookingEditScreen extends Screen
{
    public ?BookingRequest $booking = null;

    public function name(): ?string
    {
        return "Заявка #{$this->booking?->id}";
    }

    public function query(BookingRequest $booking): iterable
    {
        $booking->load(['client', 'processedBy']);

        return [
            'booking' => $booking,
        ];
    }

    public function commandBar(): iterable
    {
        return [
            Button::make('Подтвердить')
                ->icon('bs.check-circle')
                ->type(Color::SUCCESS)
                ->method('approve')
                ->canSee($this->booking?->isPending()),

            Button::make('Отклонить')
                ->icon('bs.x-circle')
                ->type(Color::DANGER)
                ->method('reject')
                ->canSee($this->booking?->isPending()),

            Link::make('Назад')
                ->icon('bs.arrow-left')
                ->route('platform.bookings'),
        ];
    }

    public function layout(): iterable
    {
        $sights = [
            Sight::make('status', 'Статус')
                ->render(fn (BookingRequest $b) =>
                    "<span class='badge bg-{$b->status->color()}'>{$b->status->label()}</span>"),
            Sight::make('client.display_name', 'Клиент'),
            Sight::make('client.phone_number', 'Телефон'),
            Sight::make('service_type', 'Тип услуги')
                ->render(fn (BookingRequest $b) => $b->service_type->label()),
            Sight::make('game_subscription_type', 'Тип подписки на игру')
                ->render(fn (BookingRequest $b) => $b->game_subscription_type?->label() ?? '-'),
            Sight::make('locker_duration_months', 'Срок аренды шкафа')
                ->render(fn (BookingRequest $b) => $b->locker_duration_months
                    ? "{$b->locker_duration_months} мес."
                    : '-'),
            Sight::make('locker_start_date', 'Начало аренды шкафа')
                ->render(fn (BookingRequest $b) => $b->locker_start_date?->format('d.m.Y') ?? '-'),
            Sight::make('created_at', 'Дата создания')
                ->render(fn (BookingRequest $b) => $b->created_at->format('d.m.Y H:i')),
            Sight::make('processedBy.name', 'Обработал'),
            Sight::make('processed_at', 'Дата обработки')
                ->render(fn (BookingRequest $b) => $b->processed_at?->format('d.m.Y H:i') ?? '-'),
        ];

        return [
            Layout::legend('booking', $sights)->title('Информация о заявке'),

            Layout::rows([
                TextArea::make('admin_notes')
                    ->title('Заметки / Причина отказа')
                    ->rows(3)
                    ->value($this->booking?->admin_notes),
            ])->title('Заметки'),
        ];
    }

    public function approve(BookingRequest $booking, BookingService $bookingService): void
    {
        $bookingService->approve($booking, auth()->user());

        Toast::success('Заявка подтверждена, подписки активированы, уведомление отправлено');
    }

    public function reject(BookingRequest $booking, Request $request, BookingService $bookingService): void
    {
        $bookingService->reject($booking, auth()->user(), $request->input('admin_notes'));

        Toast::warning('Заявка отклонена, уведомление отправлено');
    }
}
