<?php

namespace App\Orchid\Screens;

use App\Helpers\PaymentMode;
use App\Models\Setting;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class SettingsScreen extends Screen
{
    public function name(): ?string
    {
        return 'Настройки';
    }

    public function description(): ?string
    {
        return 'Системные настройки Golf Club';
    }

    public function query(): iterable
    {
        return [
            'settings' => Setting::all()->pluck('value', 'key')->toArray(),
        ];
    }

    public function commandBar(): iterable
    {
        return [
            Button::make('Сохранить')
                ->icon('bs.check')
                ->type(Color::PRIMARY)
                ->method('save'),
        ];
    }

    public function layout(): iterable
    {
        $tabs = [];

        if (PaymentMode::isWithPayment()) {
            $tabs['Тарифы'] = Layout::rows([
                Input::make('settings.game_once_price')
                    ->type('number')
                    ->step('0.01')
                    ->title('Стоимость единоразовой игры ($)')
                    ->value(Setting::getValue('game_once_price', 50)),

                Input::make('settings.game_monthly_price')
                    ->type('number')
                    ->step('0.01')
                    ->title('Стоимость месячной подписки ($)')
                    ->value(Setting::getValue('game_monthly_price', 200)),

                Input::make('settings.locker_monthly_price')
                    ->type('number')
                    ->step('0.01')
                    ->title('Стоимость аренды шкафа в месяц ($)')
                    ->value(Setting::getValue('locker_monthly_price', 10)),
            ]);

            $tabs['Реквизиты'] = Layout::rows([
                Input::make('settings.payment_card_number')
                    ->title('Номер карты для оплаты')
                    ->mask('9999 9999 9999 9999')
                    ->value(Setting::getValue('payment_card_number')),

                Input::make('settings.payment_card_holder')
                    ->title('Имя владельца карты')
                    ->value(Setting::getValue('payment_card_holder')),
            ]);
        }

        $tabs['Контакты'] = Layout::rows([
            Input::make('settings.contact_phone')
                ->title('Контактный телефон')
                ->mask('+999 99 999-99-99')
                ->value(Setting::getValue('contact_phone')),
        ]);

        $tabs['Уведомления'] = Layout::rows([
            Input::make('settings.notification_days_before')
                ->type('number')
                ->title('За сколько дней уведомлять об истечении подписки')
                ->value(Setting::getValue('notification_days_before', 3)),

            TextArea::make('settings.welcome_message')
                ->title('Приветственное сообщение')
                ->rows(3)
                ->value(Setting::getValue('welcome_message')),
        ]);

        return [
            Layout::tabs($tabs),
        ];
    }

    public function save(Request $request): void
    {
        $request->validate([
            'settings.game_once_price' => 'nullable|numeric|min:0',
            'settings.game_monthly_price' => 'nullable|numeric|min:0',
            'settings.locker_monthly_price' => 'nullable|numeric|min:0',
            'settings.notification_days_before' => 'nullable|integer|min:1|max:30',
        ]);

        $settings = $request->input('settings', []);

        foreach ($settings as $key => $value) {
            Setting::setValue($key, $value);
        }

        Toast::success('Настройки сохранены');
    }
}
