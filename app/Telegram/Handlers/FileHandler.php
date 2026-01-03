<?php

namespace App\Telegram\Handlers;

use App\Enums\BookingStatus;
use App\Models\BookingRequest;
use App\Models\Client;
use App\Models\Payment;
use Illuminate\Support\Facades\Storage;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

class FileHandler
{
    public function __construct(
        protected Api $telegram,
        protected Update $update,
        protected ?Client $client
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
        $fileId = null;
        $fileName = 'receipt';
        $fileType = 'application/octet-stream';

        if ($message->has('photo')) {
            $photos = $message->getPhoto();
            $photo = end($photos);
            $fileId = $photo['file_id'];
            $fileName = 'receipt.jpg';
            $fileType = 'image/jpeg';
        } elseif ($message->has('document')) {
            $document = $message->getDocument();
            $fileId = $document->getFileId();
            $fileName = $document->getFileName() ?? 'receipt';
            $fileType = $document->getMimeType() ?? 'application/octet-stream';
        }

        if (!$fileId) {
            $this->sendError('Не удалось получить файл.');
            return;
        }

        $filePath = $this->downloadFile($fileId, $fileName);

        if (!$filePath) {
            $this->sendError('Ошибка при загрузке файла.');
            return;
        }

        Payment::updateOrCreate(
            ['booking_request_id' => $booking->id],
            [
                'client_id' => $this->client->id,
                'amount' => $booking->total_price,
                'receipt_file_path' => $filePath,
                'receipt_file_name' => $fileName,
                'receipt_file_type' => $fileType,
                'status' => 'pending',
            ]
        );

        $booking->markPaymentSent();

        $this->sendSuccess($booking);
        $this->notifyAdmins($booking);
    }

    protected function downloadFile(string $fileId, string $fileName): ?string
    {
        try {
            $file = $this->telegram->getFile(['file_id' => $fileId]);
            $filePath = $file->getFilePath();

            $url = "https://api.telegram.org/file/bot" . 
                   config('telegram.bots.golfclub.token') . 
                   "/{$filePath}";

            $contents = file_get_contents($url);
            
            $extension = pathinfo($fileName, PATHINFO_EXTENSION) ?: 'jpg';
            $storagePath = "receipts/{$this->client->id}/" . 
                          time() . '_' . uniqid() . '.' . $extension;

            Storage::disk('public')->put($storagePath, $contents);

            return $storagePath;
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
        
        if (!$adminChatId) {
            return;
        }

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
