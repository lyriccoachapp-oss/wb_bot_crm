<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessReceiptOcr;
use App\Repositories\BotReceiptRepository;
use App\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception;

class ReceiptQueueController extends Controller
{
    private BotReceiptRepository $receiptRepo;
    private GoogleDriveService $driveService;

    public function __construct(BotReceiptRepository $receiptRepo, GoogleDriveService $driveService)
    {
        $this->receiptRepo = $receiptRepo;
        $this->driveService = $driveService;
    }

    /**
     * GET /api/v1/receipts/queue
     * Получить элементы очереди текущего пользователя
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->auth_user;
        $queue = DB::table('receipts_queue')
            ->where('user_id', $user->id)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($item) {
                if ($item->parsed_data) {
                    $item->parsed_data = json_decode($item->parsed_data, true);
                }
                return $item;
            });

        return response()->json(['success' => true, 'data' => $queue]);
    }

    /**
     * POST /api/v1/receipts/queue
     * Поместить файл в очередь
     */
    public function enqueue(Request $request): JsonResponse
    {
        $user = $request->auth_user;

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:jpg,jpeg,png,webp|max:20480',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $name = uniqid('rcpt_') . '.' . $file->getClientOriginalExtension();
        
        // Временная папка на сервере
        $dir = storage_path('app/receipts_queue');
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $localFilePath = $dir . '/' . $name;
        
        $file->move($dir, $name);

        // Создаем запись в очереди (без gdrive_id, сохраняем локальный путь)
        $queueId = DB::table('receipts_queue')->insertGetId([
            'user_id' => $user->id,
            'original_filename' => $originalName,
            'local_path' => $name,
            'gdrive_id' => null, // Загрузка в GDrive отложена до момента сохранения
            'status' => 'pending',
            'global_company' => $request->input('global_company'),
            'global_employee' => $request->input('global_employee'),
            'global_object' => $request->input('global_object'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Диспатчим Job в фоне (БЕЗ sync!)
        // Передаем путь к файлу и дату модификации файла
        $fileLastModified = $request->input('file_last_modified');
        dispatch(new ProcessReceiptOcr($queueId, $localFilePath, $fileLastModified));

        return response()->json([
            'success' => true, 
            'message' => 'Чек добавлен в очередь',
            'id' => $queueId,
            'local_path' => $name
        ]);
    }

    /**
     * GET /api/v1/receipts/queue/{id}/image
     * Получить картинку чека из локальной очереди
     */
    public function image(Request $request, int $id)
    {
        $user = $request->auth_user;
        $queueItem = DB::table('receipts_queue')->where('id', $id)->where('user_id', $user->id)->first();

        if (!$queueItem || !$queueItem->local_path) {
            return response()->json(['success' => false, 'error' => 'Элемент или файл не найден'], 404);
        }

        $path = storage_path('app/receipts_queue/' . $queueItem->local_path);
        if (!file_exists($path)) {
            return response()->json(['success' => false, 'error' => 'Файл не найден на диске'], 404);
        }

        return response()->file($path);
    }

    /**
     * POST /api/v1/receipts/queue/{id}/save
     * Сохранить распознанный чек и удалить из очереди
     */
    public function save(Request $request, int $id): JsonResponse
    {
        $user = $request->auth_user;
        $queueItem = DB::table('receipts_queue')->where('id', $id)->where('user_id', $user->id)->first();

        if (!$queueItem) {
            return response()->json(['success' => false, 'error' => 'Элемент очереди не найден'], 404);
        }

        $validator = Validator::make($request->all(), [
            'place_id'       => 'required|integer',
            'receipt_amount' => 'required|numeric',
            'receipt_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        // Проверка на дубликат чека (та же дата, то же время, та же сумма)
        // Чтобы избежать ошибки если receipt_time не передан, используем Where
        $duplicateQuery = DB::table('bot_receipts')
            ->where('receipt_date', $request->input('receipt_date'))
            ->where('receipt_amount', $request->input('receipt_amount'));
            
        if ($request->filled('receipt_time')) {
            $duplicateQuery->where('receipt_time', $request->input('receipt_time'));
        }

        if ($duplicateQuery->exists()) {
            return response()->json(['success' => false, 'error' => 'Возможно это дубликат! Чек с такой же датой, временем и суммой уже существует в базе.'], 409);
        }

        // 1. Загружаем в Google Drive
        $gdriveId = null;
        if ($queueItem->local_path) {
            $localFilePath = storage_path('app/receipts_queue/' . $queueItem->local_path);
            if (file_exists($localFilePath)) {
                try {
                    $gdriveId = $this->driveService->uploadFile(
                        $localFilePath,
                        $queueItem->original_filename,
                        "Чек от {$user->full_name} (" . date('Y-m-d H:i') . ")"
                    );
                    // Локальный файл удалим только после успешной записи в БД
                } catch (Exception $e) {
                    return response()->json(['success' => false, 'error' => 'Ошибка загрузки в Google Drive: ' . $e->getMessage()], 500);
                }
            }
        }

        $parsedData = json_decode($queueItem->parsed_data ?? '{}', true);

        // 2. Создаём чек с присланными из интерфейса данными
        $receiptData = array_merge(
            array_filter([
                'id_telegram'    => $request->input('id_telegram') ?? $user->id_telegram ?? $user->telegram_id,
                'receipt_org'    => $request->input('receipt_org') ?? $user->company_slug,
                'id_place'       => $request->input('place_id'),
                'receipt_date'   => $request->input('receipt_date'),
                'receipt_time'   => $request->input('receipt_time'),
                'merchant_name'  => $request->input('merchant_name'),
                'merchant_address' => $request->input('merchant_address'),
                'receipt_amount' => $request->input('receipt_amount'),
                'amount_subtotal'=> $request->input('subtotal'),
                'amount_tax'     => $request->input('tax'),
                'payment_method' => $request->input('payment_method'),
                'card_last4'     => $request->input('card_last4'),
                'receipt_type'   => $request->input('receipt_type'),
                'comment'        => $request->input('comment'),
                'gdrive_id'      => $gdriveId,
            ], fn ($v) => $v !== null && $v !== '')
        );

        $this->receiptRepo->create($receiptData);

        // 3. Удаляем из очереди
        DB::table('receipts_queue')->where('id', $id)->delete();

        if (isset($localFilePath) && file_exists($localFilePath)) {
            @unlink($localFilePath);
        }

        return response()->json(['success' => true, 'message' => 'Чек успешно сохранен']);
    }

    /**
     * DELETE /api/v1/receipts/queue/{id}
     * Просто удалить из очереди
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->auth_user;
        $queueItem = DB::table('receipts_queue')->where('id', $id)->where('user_id', $user->id)->first();

        if (!$queueItem) {
            return response()->json(['success' => false, 'error' => 'Элемент не найден'], 404);
        }

        if ($queueItem->local_path) {
            $localFilePath = storage_path('app/receipts_queue/' . $queueItem->local_path);
            if (file_exists($localFilePath)) {
                @unlink($localFilePath);
            }
        }

        DB::table('receipts_queue')->where('id', $id)->delete();

        return response()->json(['success' => true, 'message' => 'Удалено из очереди']);
    }
}
