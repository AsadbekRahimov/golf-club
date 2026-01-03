# Этап 6: Файловое хранилище

## Обзор этапа

**Цель:** Настроить систему хранения файлов для чеков оплаты.

**Длительность:** 1-2 дня

**Зависимости:** Этап 4 (Бизнес-логика)

**Результат:** Работающая система загрузки, хранения и отображения чеков.

---

## Чек-лист задач

- [ ] Настроить Laravel Storage
- [ ] Создать директории для хранения
- [ ] Реализовать загрузку файлов из Telegram
- [ ] Реализовать просмотр файлов в админке
- [ ] Настроить очистку старых файлов
- [ ] Добавить валидацию файлов

---

## 1. Настройка Storage

### 1.1 Конфигурация filesystems

**Файл:** `config/filesystems.php`

```php
<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        'receipts' => [
            'driver' => 'local',
            'root' => storage_path('app/receipts'),
            'url' => env('APP_URL').'/receipts',
            'visibility' => 'private',
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],
    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
```

### 1.2 Создание символической ссылки

```bash
php artisan storage:link
```

### 1.3 Создание директорий

```bash
mkdir -p storage/app/receipts
mkdir -p storage/app/public/receipts
```

---

## 2. FileService

**Файл:** `app/Services/FileService.php`

```php
<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileService
{
    protected string $disk = 'public';
    protected string $receiptsPath = 'receipts';

    /**
     * Загрузка файла чека
     */
    public function uploadReceipt(UploadedFile $file, int $clientId): array
    {
        $this->validateFile($file);

        $extension = $file->getClientOriginalExtension();
        $filename = $this->generateFilename($extension);
        $path = "{$this->receiptsPath}/{$clientId}";

        $storedPath = $file->storeAs($path, $filename, $this->disk);

        return [
            'path' => $storedPath,
            'name' => $file->getClientOriginalName(),
            'type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'url' => Storage::disk($this->disk)->url($storedPath),
        ];
    }

    /**
     * Загрузка файла из Telegram
     */
    public function uploadFromTelegram(
        string $contents,
        int $clientId,
        string $filename,
        string $mimeType
    ): array {
        $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: $this->getExtensionFromMime($mimeType);
        $newFilename = $this->generateFilename($extension);
        $path = "{$this->receiptsPath}/{$clientId}/{$newFilename}";

        Storage::disk($this->disk)->put($path, $contents);

        return [
            'path' => $path,
            'name' => $filename,
            'type' => $mimeType,
            'size' => strlen($contents),
            'url' => Storage::disk($this->disk)->url($path),
        ];
    }

    /**
     * Получить URL файла
     */
    public function getUrl(string $path): string
    {
        return Storage::disk($this->disk)->url($path);
    }

    /**
     * Получить содержимое файла
     */
    public function getContents(string $path): ?string
    {
        if (!Storage::disk($this->disk)->exists($path)) {
            return null;
        }

        return Storage::disk($this->disk)->get($path);
    }

    /**
     * Удалить файл
     */
    public function delete(string $path): bool
    {
        return Storage::disk($this->disk)->delete($path);
    }

    /**
     * Удалить директорию клиента
     */
    public function deleteClientDirectory(int $clientId): bool
    {
        $path = "{$this->receiptsPath}/{$clientId}";
        return Storage::disk($this->disk)->deleteDirectory($path);
    }

    /**
     * Проверить существование файла
     */
    public function exists(string $path): bool
    {
        return Storage::disk($this->disk)->exists($path);
    }

    /**
     * Получить размер файла
     */
    public function getSize(string $path): int
    {
        return Storage::disk($this->disk)->size($path);
    }

    /**
     * Валидация файла
     */
    protected function validateFile(UploadedFile $file): void
    {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $maxSize = 10 * 1024 * 1024; // 10MB

        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new \InvalidArgumentException(
                'Недопустимый тип файла. Разрешены: JPG, PNG, GIF, PDF'
            );
        }

        if ($file->getSize() > $maxSize) {
            throw new \InvalidArgumentException(
                'Файл слишком большой. Максимальный размер: 10MB'
            );
        }
    }

    /**
     * Генерация уникального имени файла
     */
    protected function generateFilename(string $extension): string
    {
        return date('Y-m-d_His') . '_' . Str::random(8) . '.' . $extension;
    }

    /**
     * Получить расширение по MIME типу
     */
    protected function getExtensionFromMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }

    /**
     * Очистка старых файлов
     */
    public function cleanupOldFiles(int $daysOld = 90): int
    {
        $deleted = 0;
        $cutoffDate = now()->subDays($daysOld);

        $files = Storage::disk($this->disk)->allFiles($this->receiptsPath);

        foreach ($files as $file) {
            $lastModified = Storage::disk($this->disk)->lastModified($file);
            
            if ($lastModified < $cutoffDate->timestamp) {
                Storage::disk($this->disk)->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Получить статистику хранилища
     */
    public function getStorageStats(): array
    {
        $files = Storage::disk($this->disk)->allFiles($this->receiptsPath);
        $totalSize = 0;

        foreach ($files as $file) {
            $totalSize += Storage::disk($this->disk)->size($file);
        }

        return [
            'files_count' => count($files),
            'total_size' => $totalSize,
            'total_size_formatted' => $this->formatBytes($totalSize),
        ];
    }

    /**
     * Форматирование размера файла
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
```

---

## 3. Обновление FileHandler для Telegram

**Файл:** `app/Telegram/Handlers/FileHandler.php` (обновление)

```php
<?php

namespace App\Telegram\Handlers;

use App\Enums\BookingStatus;
use App\Models\BookingRequest;
use App\Models\Client;
use App\Models\Payment;
use App\Services\FileService;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

class FileHandler
{
    public function __construct(
        protected Api $telegram,
        protected Update $update,
        protected ?Client $client,
        protected FileService $fileService
    ) {}

    public function handle(): void
    {
        if (!$this->client?->isApproved()) {
            $this->sendError('Вы не можете загружать файлы.');
            return;
        }

        $booking = BookingRequest::where('client_id', $this->client->id)
            ->where('status', BookingStatus::PAYMENT_REQUIRED)
            ->latest()
            ->first();

        if (!$booking) {
            $this->sendError('У вас нет запросов, ожидающих оплаты.');
            return;
        }

        $message = $this->update->getMessage();
        $fileData = $this->extractFileData($message);

        if (!$fileData) {
            $this->sendError('Не удалось получить файл.');
            return;
        }

        try {
            // Скачиваем файл с Telegram
            $contents = $this->downloadFile($fileData['file_id']);
            
            if (!$contents) {
                $this->sendError('Ошибка при загрузке файла.');
                return;
            }

            // Сохраняем через FileService
            $fileInfo = $this->fileService->uploadFromTelegram(
                $contents,
                $this->client->id,
                $fileData['file_name'],
                $fileData['mime_type']
            );

            // Создаем/обновляем платеж
            Payment::updateOrCreate(
                ['booking_request_id' => $booking->id],
                [
                    'client_id' => $this->client->id,
                    'amount' => $booking->total_price,
                    'receipt_file_path' => $fileInfo['path'],
                    'receipt_file_name' => $fileInfo['name'],
                    'receipt_file_type' => $fileInfo['type'],
                    'status' => 'pending',
                ]
            );

            $booking->markPaymentSent();

            $this->sendSuccess($booking);
            $this->notifyAdmins($booking);

        } catch (\Exception $e) {
            report($e);
            $this->sendError('Произошла ошибка. Попробуйте позже.');
        }
    }

    protected function extractFileData($message): ?array
    {
        if ($message->has('photo')) {
            $photos = $message->getPhoto();
            $photo = end($photos);
            
            return [
                'file_id' => $photo['file_id'],
                'file_name' => 'receipt.jpg',
                'mime_type' => 'image/jpeg',
            ];
        }

        if ($message->has('document')) {
            $document = $message->getDocument();
            
            // Проверяем тип файла
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            $mimeType = $document->getMimeType();
            
            if (!in_array($mimeType, $allowedTypes)) {
                $this->sendError('Недопустимый тип файла. Отправьте изображение (JPG, PNG) или PDF.');
                return null;
            }

            return [
                'file_id' => $document->getFileId(),
                'file_name' => $document->getFileName() ?? 'receipt',
                'mime_type' => $mimeType,
            ];
        }

        return null;
    }

    protected function downloadFile(string $fileId): ?string
    {
        try {
            $file = $this->telegram->getFile(['file_id' => $fileId]);
            $filePath = $file->getFilePath();

            $url = "https://api.telegram.org/file/bot" . 
                   config('telegram.bots.golfclub.token') . 
                   "/{$filePath}";

            return file_get_contents($url);
        } catch (\Exception $e) {
            report($e);
            return null;
        }
    }

    protected function sendError(string $message): void
    {
        $this->telegram->sendMessage([
            'chat_id' => $this->update->getMessage()->getChat()->getId(),
            'text' => "❌ {$message}",
        ]);
    }

    protected function sendSuccess(BookingRequest $booking): void
    {
        $this->telegram->sendMessage([
            'chat_id' => $this->update->getMessage()->getChat()->getId(),
            'text' => "✅ *Чек получен!*\n\n" .
                "Ваш чек по заявке #{$booking->id} отправлен на проверку.\n" .
                "Ожидайте подтверждения от администратора.",
            'parse_mode' => 'Markdown',
        ]);
    }

    protected function notifyAdmins(BookingRequest $booking): void
    {
        $adminChatId = config('telegram.admin_chat_id');
        
        if ($adminChatId) {
            $this->telegram->sendMessage([
                'chat_id' => $adminChatId,
                'text' => "💳 *Получен чек*\n\n" .
                    "👤 {$this->client->display_name}\n" .
                    "📱 {$this->client->phone_number}\n" .
                    "💰 \${$booking->total_price}\n" .
                    "🏷️ Заявка #{$booking->id}",
                'parse_mode' => 'Markdown',
            ]);
        }
    }
}
```

---

## 4. Контроллер для просмотра файлов

**Файл:** `app/Http/Controllers/ReceiptController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ReceiptController extends Controller
{
    public function __construct(
        protected FileService $fileService
    ) {}

    /**
     * Показать чек
     */
    public function show(Payment $payment): Response
    {
        $this->authorize('view', $payment);

        if (!$payment->receipt_file_path || !$this->fileService->exists($payment->receipt_file_path)) {
            abort(404, 'Файл не найден');
        }

        $contents = $this->fileService->getContents($payment->receipt_file_path);
        
        return response($contents)
            ->header('Content-Type', $payment->receipt_file_type)
            ->header('Content-Disposition', 'inline; filename="' . $payment->receipt_file_name . '"');
    }

    /**
     * Скачать чек
     */
    public function download(Payment $payment): Response
    {
        $this->authorize('view', $payment);

        if (!$payment->receipt_file_path || !$this->fileService->exists($payment->receipt_file_path)) {
            abort(404, 'Файл не найден');
        }

        return Storage::disk('public')->download(
            $payment->receipt_file_path,
            $payment->receipt_file_name
        );
    }
}
```

### 4.1 Маршруты

**Файл:** `routes/web.php` (добавить)

```php
use App\Http\Controllers\ReceiptController;

Route::middleware(['auth'])->group(function () {
    Route::get('/receipts/{payment}', [ReceiptController::class, 'show'])
        ->name('receipts.show');
    Route::get('/receipts/{payment}/download', [ReceiptController::class, 'download'])
        ->name('receipts.download');
});
```

---

## 5. Обновление Payment модели

**Файл:** `app/Models/Payment.php` (добавить методы)

```php
<?php

// Добавить к существующей модели:

/**
 * Получить URL для просмотра чека
 */
public function getReceiptViewUrlAttribute(): ?string
{
    if (!$this->receipt_file_path) {
        return null;
    }

    return route('receipts.show', $this);
}

/**
 * Получить URL для скачивания чека
 */
public function getReceiptDownloadUrlAttribute(): ?string
{
    if (!$this->receipt_file_path) {
        return null;
    }

    return route('receipts.download', $this);
}

/**
 * Проверить является ли чек изображением
 */
public function getIsImageReceiptAttribute(): bool
{
    return str_starts_with($this->receipt_file_type ?? '', 'image/');
}

/**
 * Проверить является ли чек PDF
 */
public function getIsPdfReceiptAttribute(): bool
{
    return $this->receipt_file_type === 'application/pdf';
}
```

---

## 6. Blade компонент для отображения чека

**Файл:** `resources/views/components/receipt-viewer.blade.php`

```blade
@props(['payment'])

<div class="receipt-viewer">
    @if($payment->has_receipt)
        @if($payment->is_image_receipt)
            <div class="text-center">
                <img src="{{ $payment->receipt_view_url }}" 
                     alt="Чек" 
                     class="img-fluid rounded shadow-sm"
                     style="max-height: 400px; cursor: pointer;"
                     onclick="window.open('{{ $payment->receipt_view_url }}', '_blank')">
            </div>
        @elseif($payment->is_pdf_receipt)
            <div class="text-center p-4 bg-light rounded">
                <svg class="mb-2" width="48" height="48" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5v2z"/>
                    <path d="M4.603 14.087a.81.81 0 0 1-.438-.42c-.195-.388-.13-.776.08-1.102.198-.307.526-.568.897-.787a7.68 7.68 0 0 1 1.482-.645 19.697 19.697 0 0 0 1.062-2.227 7.269 7.269 0 0 1-.43-1.295c-.086-.4-.119-.796-.046-1.136.075-.354.274-.672.65-.823.192-.077.4-.12.602-.077a.7.7 0 0 1 .477.365c.088.164.12.356.127.538.007.188-.012.396-.047.614-.084.51-.27 1.134-.52 1.794a10.954 10.954 0 0 0 .98 1.686 5.753 5.753 0 0 1 1.334.05c.364.066.734.195.96.465.12.144.193.32.2.518.007.192-.047.382-.138.563a1.04 1.04 0 0 1-.354.416.856.856 0 0 1-.51.138c-.331-.014-.654-.196-.933-.417a5.712 5.712 0 0 1-.911-.95 11.651 11.651 0 0 0-1.997.406 11.307 11.307 0 0 1-1.02 1.51c-.292.35-.609.656-.927.787a.793.793 0 0 1-.58.029z"/>
                </svg>
                <p class="mb-0">{{ $payment->receipt_file_name }}</p>
            </div>
        @else
            <div class="text-center p-4 bg-light rounded">
                <svg class="mb-2" width="48" height="48" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5L14 4.5zm-3 0A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4.5h-2z"/>
                </svg>
                <p class="mb-0">{{ $payment->receipt_file_name }}</p>
            </div>
        @endif
        
        <div class="mt-3 text-center">
            <a href="{{ $payment->receipt_view_url }}" 
               target="_blank" 
               class="btn btn-outline-primary btn-sm me-2">
                <svg width="16" height="16" fill="currentColor" class="me-1" viewBox="0 0 16 16">
                    <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0z"/>
                    <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8zm8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/>
                </svg>
                Открыть
            </a>
            <a href="{{ $payment->receipt_download_url }}" 
               class="btn btn-outline-secondary btn-sm">
                <svg width="16" height="16" fill="currentColor" class="me-1" viewBox="0 0 16 16">
                    <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                    <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                </svg>
                Скачать
            </a>
        </div>
    @else
        <div class="text-center text-muted p-4">
            <svg width="48" height="48" fill="currentColor" class="mb-2" viewBox="0 0 16 16">
                <path d="M14 4.5V9h-1V4.5h-2A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v7H2V2a2 2 0 0 1 2-2h5.5L14 4.5zM2 12h2v1H2v-1zm4 0h2v1H6v-1zm4 0h2v1h-2v-1zm4 0h2v1h-2v-1z"/>
            </svg>
            <p class="mb-0">Чек не загружен</p>
        </div>
    @endif
</div>
```

---

## 7. Команда очистки старых файлов

**Файл:** `app/Console/Commands/CleanupOldReceipts.php`

```php
<?php

namespace App\Console\Commands;

use App\Services\FileService;
use Illuminate\Console\Command;

class CleanupOldReceipts extends Command
{
    protected $signature = 'receipts:cleanup {--days=90 : Удалить файлы старше N дней}';
    protected $description = 'Удалить старые файлы чеков';

    public function handle(FileService $fileService): int
    {
        $days = (int) $this->option('days');

        $this->info("Удаление файлов старше {$days} дней...");

        $deleted = $fileService->cleanupOldFiles($days);

        $this->info("Удалено файлов: {$deleted}");

        // Показать статистику
        $stats = $fileService->getStorageStats();
        $this->table(
            ['Метрика', 'Значение'],
            [
                ['Файлов', $stats['files_count']],
                ['Размер', $stats['total_size_formatted']],
            ]
        );

        return 0;
    }
}
```

### 7.1 Добавить в Scheduler

```php
// routes/console.php или app/Console/Kernel.php

Schedule::command('receipts:cleanup --days=90')
    ->weekly()
    ->sundays()
    ->at('03:00');
```

---

## 8. Команды для выполнения

```bash
# 1. Создать символическую ссылку
php artisan storage:link

# 2. Создать директории
mkdir -p storage/app/public/receipts

# 3. Установить права
chmod -R 775 storage/app/public/receipts

# 4. Создать FileService
# (скопировать код из документации)

# 5. Создать контроллер
php artisan make:controller ReceiptController

# 6. Создать команду очистки
php artisan make:command CleanupOldReceipts

# 7. Проверить хранилище
php artisan tinker
>>> Storage::disk('public')->allFiles('receipts')
```

---

## 9. Критерии завершения этапа

- [ ] Storage настроен корректно
- [ ] Символическая ссылка создана
- [ ] Файлы загружаются через Telegram
- [ ] Файлы отображаются в админке
- [ ] Файлы можно скачать
- [ ] Валидация типов файлов работает
- [ ] Очистка старых файлов работает
- [ ] Права доступа настроены правильно
