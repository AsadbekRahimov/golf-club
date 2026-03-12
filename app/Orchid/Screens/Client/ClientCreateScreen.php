<?php

namespace App\Orchid\Screens\Client;

use App\Enums\ClientStatus;
use App\Models\Client;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class ClientCreateScreen extends Screen
{
    public function name(): ?string
    {
        return 'Новый клиент';
    }

    public function description(): ?string
    {
        return 'Создание клиента вручную администратором';
    }

    public function query(): iterable
    {
        return [];
    }

    public function commandBar(): iterable
    {
        return [
            Link::make('Отмена')
                ->icon('bs.arrow-left')
                ->route('platform.clients'),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::rows([
                Input::make('client.phone_number')
                    ->title('Номер телефона')
                    ->mask('+998 99 999-99-99')
                    ->placeholder('+998 90 123-45-67')
                    ->required()
                    ->help('Обязательное поле. При регистрации через Telegram клиент будет автоматически привязан по этому номеру.'),

                Input::make('client.full_name')
                    ->title('Полное имя')
                    ->placeholder('Фамилия Имя Отчество'),

                Select::make('client.status')
                    ->title('Статус')
                    ->options([
                        ClientStatus::APPROVED->value => ClientStatus::APPROVED->label(),
                        ClientStatus::PENDING->value  => ClientStatus::PENDING->label(),
                    ])
                    ->value(ClientStatus::APPROVED->value)
                    ->help('Клиент со статусом "Подтверждён" сразу получает доступ при регистрации через Telegram.'),

                TextArea::make('client.notes')
                    ->title('Заметки')
                    ->rows(3)
                    ->placeholder('Дополнительная информация о клиенте'),

                Button::make('Сохранить')
                    ->icon('bs.check')
                    ->type(Color::PRIMARY)
                    ->method('store'),
            ])->title('Данные клиента'),
        ];
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'client.phone_number' => 'required|string|max:50|unique:clients,phone_number',
            'client.full_name'    => 'nullable|string|max:255',
            'client.status'       => 'required|string|in:approved,pending',
            'client.notes'        => 'nullable|string|max:1000',
        ]);

        $attrs = $data['client'];

        if ($attrs['status'] === ClientStatus::APPROVED->value) {
            $attrs['approved_by'] = auth()->id();
            $attrs['approved_at'] = now();
        }

        $client = Client::create($attrs);

        Toast::success("Клиент {$client->display_name} успешно создан");

        return redirect()->route('platform.clients.edit', $client);
    }
}
