<?php

use Illuminate\Support\Facades\Schedule;

// Проверка истекающих подписок каждый день в 9:00
Schedule::command('subscriptions:check-expiring')->dailyAt('09:00');

// Обработка истекших подписок каждый день в 00:05
Schedule::command('subscriptions:process-expired')->dailyAt('00:05');

// Ежедневный отчёт + бэкап в Telegram в 23:50
Schedule::command('report:daily')->dailyAt('23:50');
