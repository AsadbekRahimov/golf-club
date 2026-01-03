<?php

namespace App\Orchid\Screens\Booking;

use App\Enums\BookingStatus;
use App\Models\BookingRequest;
use App\Models\Payment;
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
        $booking->load(['client', 'payment', 'processedBy']);

        return [
            'booking' => $booking,
        ];
    }

    public function commandBar(): iterable
    {
        return [
            Button::make('Подтвердить без оплаты')
                ->icon('bs.check-circle')
                ->type(Color::SUCCESS)
                ->method('approveWithoutPayment')
                ->canSee($this->booking?->isPending()),

            Button::make('Запросить оплату')
                ->icon('bs.credit-card')
                ->type(Color::INFO)
                ->method('requirePayment')
                ->canSee($this->booking?->isPending()),

            Button::make('Подтвердить оплату')
                ->icon('bs.check2-all')
                ->type(Color::SUCCESS)
                ->method('verifyPayment')
                ->canSee($this->booking?->status === BookingStatus::PAYMENT_SENT && $this->booking?->payment),

            Button::make('Отклонить')
                ->icon('bs.x-circle')
                ->type(Color::DANGER)
                ->method('reject')
                ->canSee(in_array($this->booking?->status, [BookingStatus::PENDING, BookingStatus::PAYMENT_SENT])),

            Link::make('Назад')
                ->icon('bs.arrow-left')
                ->route('platform.bookings'),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::legend('booking', [
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
                Sight::make('total_price', 'Сумма')
                    ->render(fn (BookingRequest $b) => '$' . number_format($b->total_price, 2)),
                Sight::make('created_at', 'Дата создания')
                    ->render(fn (BookingRequest $b) => $b->created_at->format('d.m.Y H:i')),
                Sight::make('processedBy.name', 'Обработал'),
                Sight::make('processed_at', 'Дата обработки')
                    ->render(fn (BookingRequest $b) => $b->processed_at?->format('d.m.Y H:i') ?? '-'),
            ])->title('Информация о заявке'),

            Layout::view('platform.booking-payment', [
                'payment' => $this->booking?->payment,
            ]),

            Layout::rows([
                TextArea::make('admin_notes')
                    ->title('Заметки / Причина отказа')
                    ->rows(3)
                    ->value($this->booking?->admin_notes),
            ])->title('Заметки'),
        ];
    }

    public function approveWithoutPayment(BookingRequest $booking, BookingService $bookingService): void
    {
        $bookingService->approveWithoutPayment($booking, auth()->user());

        Toast::success('Заявка подтверждена, подписки активированы, уведомление отправлено');
    }

    public function requirePayment(BookingRequest $booking, BookingService $bookingService): void
    {
        $bookingService->requirePayment($booking, auth()->user());

        Toast::info('Клиенту отправлен запрос на оплату');
    }

    public function verifyPayment(BookingRequest $booking, BookingService $bookingService): void
    {
        $bookingService->verifyPayment($booking->payment, auth()->user());

        Toast::success('Оплата подтверждена, подписки активированы');
    }

    public function reject(BookingRequest $booking, Request $request, BookingService $bookingService): void
    {
        $bookingService->reject($booking, auth()->user(), $request->input('admin_notes'));

        Toast::warning('Заявка отклонена, уведомление отправлено');
    }
}
