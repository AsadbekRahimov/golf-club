<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'payment_card_number',
                'value' => null,
                'type' => 'string',
                'group' => 'payment',
                'description' => 'Номер карты для приема платежей',
            ],
            [
                'key' => 'payment_card_holder',
                'value' => null,
                'type' => 'string',
                'group' => 'payment',
                'description' => 'Имя владельца карты',
            ],
            [
                'key' => 'contact_phone',
                'value' => null,
                'type' => 'string',
                'group' => 'contact',
                'description' => 'Контактный телефон администрации',
            ],
            [
                'key' => 'game_once_price',
                'value' => '50.00',
                'type' => 'decimal',
                'group' => 'pricing',
                'description' => 'Стоимость единоразовой игры ($)',
            ],
            [
                'key' => 'game_monthly_price',
                'value' => '200.00',
                'type' => 'decimal',
                'group' => 'pricing',
                'description' => 'Стоимость месячной подписки ($)',
            ],
            [
                'key' => 'locker_monthly_price',
                'value' => '10.00',
                'type' => 'decimal',
                'group' => 'pricing',
                'description' => 'Стоимость аренды шкафа в месяц ($)',
            ],
            [
                'key' => 'notification_days_before',
                'value' => '3',
                'type' => 'integer',
                'group' => 'notifications',
                'description' => 'За сколько дней уведомлять об истечении подписки',
            ],
            [
                'key' => 'welcome_message',
                'value' => 'Добро пожаловать в гольф-клуб! Ваша заявка на регистрацию отправлена.',
                'type' => 'text',
                'group' => 'messages',
                'description' => 'Приветственное сообщение для новых клиентов',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
