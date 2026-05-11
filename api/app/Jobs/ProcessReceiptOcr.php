<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use App\Services\OcrService;
use Exception;

class ProcessReceiptOcr implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 минут на всякий случай
    public $tries = 1;

    protected int $queueId;
    protected string $localFilePath;
    protected ?string $fileLastModified;

    public function __construct(int $queueId, string $localFilePath, ?string $fileLastModified = null)
    {
        $this->queueId = $queueId;
        $this->localFilePath = $localFilePath;
        $this->fileLastModified = $fileLastModified;
    }

    public function handle(OcrService $ocrService): void
    {
        // 1. Помечаем как processing
        DB::table('receipts_queue')->where('id', $this->queueId)->update(['status' => 'processing']);

        try {
            if (!file_exists($this->localFilePath)) {
                throw new Exception("Файл не найден на диске: {$this->localFilePath}");
            }

            // 2. Распознаем
            $parsed = $ocrService->recognizeReceipt($this->localFilePath, $this->fileLastModified);

            // 3. Сохраняем успешный результат
            DB::table('receipts_queue')->where('id', $this->queueId)->update([
                'status' => 'ready',
                'parsed_data' => json_encode($parsed, JSON_UNESCAPED_UNICODE)
            ]);

        } catch (Exception $e) {
            // 4. При ошибке сохраняем сообщение
            DB::table('receipts_queue')->where('id', $this->queueId)->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
        }
    }
}
