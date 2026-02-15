<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\BookingRequest;
use App\Models\Client;
use App\Models\Locker;
use App\Models\Payment;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;

class SendDailyReport extends Command
{
    protected $signature = 'report:daily {--test : Тестовый запуск без отправки}';

    protected $description = 'Отправка ежедневного отчёта и бэкапа в Telegram';

    protected string $reportDate;
    protected string $reportsPath;

    public function handle(): int
    {
        $this->reportDate = Carbon::now()->format('Y-m-d');
        $this->reportsPath = storage_path('app/reports/' . $this->reportDate);

        $this->info('🚀 Начинаем формирование ежедневного отчёта...');

        try {
            if (!is_dir($this->reportsPath)) {
                mkdir($this->reportsPath, 0755, true);
            }

            $this->info('📊 Генерация Excel отчётов...');
            $excelFiles = $this->generateExcelReports();

            $this->info('💾 Создание бэкапа базы данных...');
            $backupFile = $this->createDatabaseBackup();

            $this->info('📤 Отправка в Telegram...');

            if ($this->option('test')) {
                $this->warn('⚠️ Тестовый режим - отправка пропущена');
                $this->showSummary($excelFiles, $backupFile);
            } else {
                $this->sendToTelegram($excelFiles, $backupFile);
            }

            $this->info('✅ Ежедневный отчёт успешно сформирован и отправлен!');

            $this->cleanup($excelFiles, $backupFile);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Ошибка: ' . $e->getMessage());
            Log::error('Daily report error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    protected function generateExcelReports(): array
    {
        $files = [];

        $files['clients'] = $this->generateClientsReport();
        $files['subscriptions'] = $this->generateSubscriptionsReport();
        $files['lockers'] = $this->generateLockersReport();
        $files['bookings_today'] = $this->generateBookingsTodayReport();
        $files['payments_today'] = $this->generatePaymentsTodayReport();

        return $files;
    }

    protected function generateClientsReport(): string
    {
        $filePath = $this->reportsPath . '/clients_full_' . $this->reportDate . '.xlsx';

        $clients = Client::with('approvedBy')->orderBy('created_at', 'desc')->get();

        (new FastExcel($clients))->export($filePath, function ($client) {
            return [
                'ID' => $client->id,
                'Телефон' => $client->phone_number,
                'Имя' => $client->first_name,
                'Фамилия' => $client->last_name,
                'Username' => $client->username ?? '-',
                'Статус' => $client->status->label(),
                'Telegram ID' => $client->telegram_id,
                'Дата регистрации' => $client->created_at->format('d.m.Y H:i'),
                'Дата подтверждения' => $client->approved_at?->format('d.m.Y H:i') ?? '-',
                'Заметки' => $client->notes ?? '-',
            ];
        });

        $this->line("  ✓ Клиенты: " . $clients->count() . " записей");
        return $filePath;
    }

    protected function generateSubscriptionsReport(): string
    {
        $filePath = $this->reportsPath . '/subscriptions_full_' . $this->reportDate . '.xlsx';

        $subscriptions = Subscription::with(['client', 'locker'])->orderBy('created_at', 'desc')->get();

        (new FastExcel($subscriptions))->export($filePath, function ($sub) {
            return [
                'ID' => $sub->id,
                'Клиент' => $sub->client->display_name ?? '-',
                'Телефон' => $sub->client->phone_number ?? '-',
                'Тип подписки' => $sub->subscription_type->label(),
                'Шкаф #' => $sub->locker?->locker_number ?? '-',
                'Цена ($)' => number_format($sub->price, 2),
                'Статус' => $sub->status->label(),
                'Дата начала' => $sub->start_date->format('d.m.Y'),
                'Дата окончания' => $sub->end_date?->format('d.m.Y') ?? 'Бессрочно',
                'Осталось дней' => $sub->days_remaining ?? '-',
            ];
        });

        $this->line("  ✓ Подписки: " . $subscriptions->count() . " записей");
        return $filePath;
    }

    protected function generateLockersReport(): string
    {
        $filePath = $this->reportsPath . '/lockers_full_' . $this->reportDate . '.xlsx';

        $lockers = Locker::with(['currentSubscription.client'])->orderBy('locker_number')->get();

        (new FastExcel($lockers))->export($filePath, function ($locker) {
            return [
                'Номер шкафа' => $locker->locker_number,
                'Статус' => $locker->status->label(),
                'Клиент' => $locker->currentSubscription?->client?->display_name ?? '-',
                'Телефон клиента' => $locker->currentSubscription?->client?->phone_number ?? '-',
                'Аренда до' => $locker->currentSubscription?->end_date?->format('d.m.Y') ?? '-',
            ];
        });

        $this->line("  ✓ Шкафы: " . $lockers->count() . " записей");
        return $filePath;
    }

    protected function generateBookingsTodayReport(): string
    {
        $filePath = $this->reportsPath . '/bookings_today_' . $this->reportDate . '.xlsx';

        $today = Carbon::today();
        $bookings = BookingRequest::with(['client', 'processedBy'])
            ->whereDate('created_at', $today)
            ->orderBy('created_at', 'desc')
            ->get();

        (new FastExcel($bookings))->export($filePath, function ($booking) {
            return [
                'ID' => $booking->id,
                'Клиент' => $booking->client->display_name ?? '-',
                'Телефон' => $booking->client->phone_number ?? '-',
                'Тип услуги' => $booking->service_type->label(),
                'Сумма ($)' => number_format($booking->total_price, 2),
                'Статус' => $booking->status->label(),
                'Дата создания' => $booking->created_at->format('d.m.Y H:i'),
                'Обработал' => $booking->processedBy?->name ?? '-',
            ];
        });

        $this->line("  ✓ Бронирования за сегодня: " . $bookings->count() . " записей");
        return $filePath;
    }

    protected function generatePaymentsTodayReport(): string
    {
        $filePath = $this->reportsPath . '/payments_today_' . $this->reportDate . '.xlsx';

        $today = Carbon::today();
        $payments = Payment::with(['client', 'verifiedBy'])
            ->whereDate('created_at', $today)
            ->orderBy('created_at', 'desc')
            ->get();

        (new FastExcel($payments))->export($filePath, function ($payment) {
            return [
                'ID' => $payment->id,
                'Клиент' => $payment->client->display_name ?? '-',
                'Телефон' => $payment->client->phone_number ?? '-',
                'Сумма ($)' => number_format($payment->amount, 2),
                'Статус' => $payment->status->label(),
                'Чек' => $payment->has_receipt ? 'Да' : 'Нет',
                'Дата создания' => $payment->created_at->format('d.m.Y H:i'),
                'Проверил' => $payment->verifiedBy?->name ?? '-',
            ];
        });

        $this->line("  ✓ Платежи за сегодня: " . $payments->count() . " записей");
        return $filePath;
    }

    protected function createDatabaseBackup(): ?string
    {
        Artisan::call('backup:run', [
            '--only-db' => true,
            '--disable-notifications' => true,
        ]);

        $this->line("  ✓ Бэкап базы данных создан");

        $backupPath = config('backup.backup.destination.disks')[0] ?? 'local';
        $backupDir = storage_path('app/' . config('backup.backup.name', env('APP_NAME', 'laravel-backup')));

        $latestBackup = $this->findLatestBackup($backupDir);

        if (!$latestBackup) {
            $latestBackup = $this->findLatestBackupInStorage();
        }

        return $latestBackup;
    }

    protected function findLatestBackup(string $dir): ?string
    {
        if (!is_dir($dir)) {
            return null;
        }

        $files = glob($dir . '/*.zip');
        if (empty($files)) {
            return null;
        }

        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        return $files[0] ?? null;
    }

    protected function findLatestBackupInStorage(): ?string
    {
        $possiblePaths = [
            storage_path('app/Golf Club'),
            storage_path('app/GolfClub'),
            storage_path('app/' . env('APP_NAME', 'Golf Club')),
            storage_path('app/backups'),
        ];

        foreach ($possiblePaths as $path) {
            $backup = $this->findLatestBackup($path);
            if ($backup) {
                return $backup;
            }
        }

        $allZips = glob(storage_path('app/**/*.zip'));
        if (!empty($allZips)) {
            usort($allZips, function ($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            return $allZips[0];
        }

        return null;
    }

    protected function sendToTelegram(array $excelFiles, ?string $backupFile): void
    {
        $chatId = config('telegram.admin_chat_id');
        $token = config('telegram.bots.golfclub.token');

        if (!$chatId || !$token) {
            throw new \Exception('Telegram credentials not configured');
        }

        $summary = $this->generateTextSummary();
        $this->sendTelegramMessage($token, $chatId, $summary);

        foreach ($excelFiles as $name => $filePath) {
            if (file_exists($filePath)) {
                $this->sendTelegramDocument($token, $chatId, $filePath, $this->getFileCaption($name));
            }
        }

        if ($backupFile && file_exists($backupFile)) {
            $fileSize = filesize($backupFile);

            if ($fileSize > 50 * 1024 * 1024) {
                $this->sendTelegramMessage($token, $chatId, "⚠️ Бэкап слишком большой для отправки в Telegram ({$this->formatBytes($fileSize)}). Сохранён локально: " . basename($backupFile));
            } else {
                $this->sendTelegramDocument($token, $chatId, $backupFile, "💾 Бэкап базы данных\n📅 " . $this->reportDate);
            }
        }

        $this->line("  ✓ Отправлено в Telegram");
    }

    protected function generateTextSummary(): string
    {
        $today = Carbon::today();

        $totalClients = Client::count();
        $approvedClients = Client::approved()->count();
        $pendingClients = Client::pending()->count();

        $activeSubscriptions = Subscription::active()->count();
        $expiringSubscriptions = Subscription::expiring()->count();

        $totalLockers = Locker::count();
        $availableLockers = Locker::available()->count();
        $occupiedLockers = Locker::occupied()->count();

        $todayBookings = BookingRequest::whereDate('created_at', $today)->count();
        $todayPayments = Payment::whereDate('created_at', $today)->count();
        $todayRevenue = Payment::where('status', PaymentStatus::VERIFIED)
            ->whereDate('verified_at', $today)
            ->sum('amount');

        $todayNewClients = Client::whereDate('created_at', $today)->count();

        return "📊 *ЕЖЕДНЕВНЫЙ ОТЧЁТ GOLF CLUB*\n" .
               "📅 {$this->reportDate}\n\n" .

               "👥 *КЛИЕНТЫ*\n" .
               "├ Всего: {$totalClients}\n" .
               "├ Подтверждённых: {$approvedClients}\n" .
               "├ Ожидают: {$pendingClients}\n" .
               "└ Новых сегодня: {$todayNewClients}\n\n" .

               "📋 *ПОДПИСКИ*\n" .
               "├ Активных: {$activeSubscriptions}\n" .
               "└ Истекает скоро: {$expiringSubscriptions}\n\n" .

               "🗄 *ШКАФЫ*\n" .
               "├ Всего: {$totalLockers}\n" .
               "├ Свободно: {$availableLockers}\n" .
               "└ Занято: {$occupiedLockers}\n\n" .

               "📈 *ЗА СЕГОДНЯ*\n" .
               "├ Бронирований: {$todayBookings}\n" .
               "├ Платежей: {$todayPayments}\n" .
               "└ Выручка: \${$todayRevenue}\n\n" .

               "📎 Файлы отчётов прилагаются ниже ⬇️";
    }

    protected function getFileCaption(string $name): string
    {
        $captions = [
            'clients' => "👥 Полный список клиентов\n📅 " . $this->reportDate,
            'subscriptions' => "📋 Полный список подписок\n📅 " . $this->reportDate,
            'lockers' => "🗄 Полный список шкафов\n📅 " . $this->reportDate,
            'bookings_today' => "📝 Бронирования за сегодня\n📅 " . $this->reportDate,
            'payments_today' => "💰 Платежи за сегодня\n📅 " . $this->reportDate,
        ];

        return $captions[$name] ?? "📄 Отчёт {$name}\n📅 " . $this->reportDate;
    }

    protected function sendTelegramMessage(string $token, string $chatId, string $text): void
    {
        $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);

        if (!$response->successful()) {
            Log::warning('Telegram message failed', ['response' => $response->json()]);
        }
    }

    protected function sendTelegramDocument(string $token, string $chatId, string $filePath, string $caption): void
    {
        $response = Http::attach(
            'document',
            file_get_contents($filePath),
            basename($filePath)
        )->post("https://api.telegram.org/bot{$token}/sendDocument", [
            'chat_id' => $chatId,
            'caption' => $caption,
        ]);

        if (!$response->successful()) {
            Log::warning('Telegram document failed', [
                'file' => $filePath,
                'response' => $response->json()
            ]);
        }
    }

    protected function showSummary(array $excelFiles, ?string $backupFile): void
    {
        $this->newLine();
        $this->info('📋 Сгенерированные файлы:');

        foreach ($excelFiles as $name => $path) {
            $size = file_exists($path) ? $this->formatBytes(filesize($path)) : 'N/A';
            $this->line("  - {$name}: {$path} ({$size})");
        }

        if ($backupFile) {
            $size = file_exists($backupFile) ? $this->formatBytes(filesize($backupFile)) : 'N/A';
            $this->line("  - backup: {$backupFile} ({$size})");
        }

        $this->newLine();
        $this->info('📊 Текстовая сводка:');
        $this->line($this->generateTextSummary());
    }

    protected function cleanup(array $excelFiles, ?string $backupFile): void
    {
        foreach ($excelFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }

        if (is_dir($this->reportsPath)) {
            @rmdir($this->reportsPath);
        }
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
