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
                'key' => 'contact_phone',
                'value' => null,
                'type' => 'string',
                'group' => 'contact',
                'description' => 'Контактный телефон администрации',
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
