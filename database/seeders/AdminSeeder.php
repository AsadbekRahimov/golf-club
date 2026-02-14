<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Admin',
                'email' => 'admin@admin.com',
                'password' => Hash::make('password'),
                'permissions' => [
                    'platform.index' => true,
                    'platform.systems.roles' => true,
                    'platform.systems.users' => true,
                    'platform.clients' => true,
                    'platform.bookings' => true,
                    'platform.payments' => true,
                    'platform.lockers' => true,
                    'platform.subscriptions' => true,
                    'platform.settings' => true,
                    'platform.attachments' => true,
                ],
            ]
        );
    }
}
