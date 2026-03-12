<?php

namespace App\Orchid\Screens;

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

    public function query(): iterable
    {
        return [];
    }

    public function commandBar(): iterable
    {
        return [
            Button::make('Сохранить')
                ->icon('bs.check')
                ->type(Color::SUCCESS)
                ->method('save'),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::tabs([
                'Контакты' => Layout::rows([
                    Input::make('settings.contact_phone')
                        ->title('Контактный телефон')
                        ->mask('+999 99 999-99-99')
                        ->value(Setting::getValue('contact_phone')),
                ]),

                'Уведомления' => Layout::rows([
                    Input::make('settings.notification_days_before')
                        ->type('number')
                        ->title('За сколько дней уведомлять об истечении подписки')
                        ->value(Setting::getValue('notification_days_before', 3)),

                    TextArea::make('settings.welcome_message')
                        ->title('Приветственное сообщение')
                        ->rows(3)
                        ->value(Setting::getValue('welcome_message')),
                ]),
            ]),
        ];
    }

    public function save(Request $request): void
    {
        $settings = $request->input('settings', []);

        foreach ($settings as $key => $value) {
            Setting::setValue($key, $value);
        }

        Toast::success('Настройки сохранены');
    }
}
