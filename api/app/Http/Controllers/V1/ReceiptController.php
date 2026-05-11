<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Repositories\BotReceiptRepository;
use App\Services\OcrService;
use App\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Контроллер чеков
 *
 * Загрузка, OCR распознавание, сохранение на Google Drive, просмотр чеков.
 */
class ReceiptController extends Controller
{
	public function __construct(
		private readonly BotReceiptRepository $receiptRepo,
		private readonly OcrService           $ocrService,
		private readonly GoogleDriveService   $driveService
	) {
	}

	/**
	 * GET /api/v1/receipts
	 *
	 * Список чеков с фильтрами.
	 */
	public function index(Request $request): JsonResponse
	{
		$user = $request->auth_user;
		$page  = (int)$request->query('page',  1);
		$limit = (int)$request->query('limit', 20);

		$filters = [];

		// Сотрудник видит только свои чеки
		if (!$user->isAdmin() && !$user->hasPermission('receipts.view_all')) {
			$filters['telegram_id'] = $user->id_telegram;
		} elseif ($request->has('telegram_id')) {
			$filters['telegram_id'] = (int)$request->query('telegram_id');
		}

		if ($request->has('place_id'))  $filters['place_id']  = (int)$request->query('place_id');
		if ($request->has('date_from')) $filters['date_from'] = $request->query('date_from');
		if ($request->has('date_to'))   $filters['date_to']   = $request->query('date_to');

		$paginator = $this->receiptRepo->paginate($filters, $page, $limit);

		return $this->paginated($paginator, fn ($r) => $this->formatReceipt($r));
	}

	/**
	 * GET /api/v1/receipts/{id}
	 *
	 * Получить чек.
	 */
	public function show(int $id): JsonResponse
	{
		$receipt = $this->receiptRepo->findById($id);

		if (!$receipt) {
			return $this->error('Чек не найден.', 404);
		}

		return $this->success($this->formatReceipt($receipt));
	}

	/**
	 * POST /api/v1/receipts
	 *
	 * Создать чек вручную.
	 */
	public function store(Request $request): JsonResponse
	{
		$user = $request->auth_user;

		$validator = Validator::make($request->all(), [
			'place_id'       => 'nullable|integer',
			'receipt_date'   => 'required|date_format:Y-m-d',
			'receipt_amount' => 'required|numeric|min:0',
			'merchant_name'  => 'nullable|string|max:255',
			'receipt_type'   => 'nullable|string|in:fuel,materials,groceries,tools,restaurant,other',
			'payment_method' => 'nullable|string|in:cash,card',
		]);

		if ($validator->fails()) {
			return $this->error($validator->errors()->first(), 422);
		}

		$receipt = $this->receiptRepo->create(array_merge(
			$request->only([
				'place_id', 'receipt_date', 'receipt_amount',
				'merchant_name', 'merchant_address', 'receipt_type',
				'payment_method', 'card_last4',
			]),
			['id_telegram' => $user->id_telegram ?? $user->telegram_id, 'receipt_org' => $user->company_slug]
		));

		return $this->success($this->formatReceipt($receipt), 'Чек создан.', 201);
	}

	/**
	 * POST /api/v1/receipts/recognize
	 *
	 * Загрузить фото чека и вернуть OCR JSON без сохранения в базу и Drive.
	 */
	public function recognize(Request $request): JsonResponse
	{
		$validator = Validator::make($request->all(), [
			'file' => 'required|file|mimes:jpg,jpeg,png,webp|max:20480',
		]);

		if ($validator->fails()) {
			return $this->error($validator->errors()->first(), 422);
		}

		$file = $request->file('file');
		$path = $file->getPathname();

		try {
			$parsed = $this->ocrService->recognizeReceipt($path);
			return $this->success(['parsed' => $parsed], 'Чек успешно распознан.');
		} catch (\Exception $e) {
			return $this->error('Ошибка распознавания: ' . $e->getMessage(), 500);
		}
	}

	/**
	 * POST /api/v1/receipts/upload
	 *
	 * Окончательно сохранить фото чека + данные на Google Drive и БД.
	 */
	public function upload(Request $request): JsonResponse
	{
		$user = $request->auth_user;

		$validator = Validator::make($request->all(), [
			'file'           => 'required|file|max:20480',
			'place_id'       => 'required|integer',
			'receipt_amount' => 'required|numeric',
			'receipt_date'   => 'required|date',
		]);

		if ($validator->fails()) {
			return $this->error($validator->errors()->first(), 422);
		}

		// Сохраняем файл
		$file    = $request->file('file');
		$dir     = storage_path('uploads/receipts');
		$name    = uniqid('rcpt_') . '.' . $file->getClientOriginalExtension();
		$path    = $dir . '/' . $name;

		if (!is_dir($dir)) mkdir($dir, 0755, true);
		$file->move($dir, $name);

		// Загружаем фото на Google Drive
		$gdriveId = $this->driveService->uploadFile(
			$path,
			$name,
			"Чек от {$user->botUser()?->full_name} (" . date('Y-m-d H:i') . ")"
		);

		// Создаём чек с присланными из интерфейса данными
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
				'subtotal'       => $request->input('subtotal'),
				'tax'            => $request->input('tax'),
				'currency'       => $request->input('currency', 'CAD'),
				'payment_method' => $request->input('payment_method'),
				'card_last4'     => $request->input('card_last4'),
				'receipt_type'   => $request->input('receipt_type'),
				'items_json'     => json_decode($request->input('items_json', '[]'), true),
				'ocr_text'       => $request->input('ocr_text'),
				'comment'        => $request->input('comment'),
				'gdrive_id'      => $gdriveId,
			], fn ($v) => $v !== null && $v !== '')
		);

		$receipt = $this->receiptRepo->create($receiptData);

		return $this->success([
			'receipt' => $this->formatReceipt($receipt)
		], 'Чек загружен и сохранен.', 201);
	}

	/**
	 * PUT /api/v1/receipts/{id}
	 *
	 * Обновить чек.
	 */
	public function update(Request $request, int $id): JsonResponse
	{
		$receipt = $this->receiptRepo->findById($id);

		if (!$receipt) {
			return $this->error('Чек не найден.', 404);
		}

		$updated = $this->receiptRepo->update($receipt, $request->only([
			'place_id', 'receipt_date', 'receipt_time',
			'merchant_name', 'merchant_address',
			'receipt_amount', 'subtotal', 'tax', 'currency',
			'payment_method', 'card_last4', 'receipt_type',
			'items_json',
		]));

		return $this->success($this->formatReceipt($updated), 'Чек обновлён.');
	}

	/**
	 * Форматировать чек для ответа
	 */
	private function formatReceipt(mixed $r): array
	{
		return [
			'id'             => $r->id_receipt,
			'telegram_id'    => $r->id_telegram,
			'place_id'       => $r->id_place,
			'place_name'     => $r->place?->place_name,
			'date'           => $r->receipt_date,
			'time'           => $r->receipt_time,
			'merchant_name'  => $r->merchant_name,
			'merchant_address' => $r->merchant_address,
			'amount'         => $r->receipt_amount,
			'subtotal'       => $r->subtotal,
			'tax'            => $r->tax,
			'currency'       => $r->currency,
			'payment_method' => $r->payment_method,
			'card_last4'     => $r->card_last4,
			'category'       => $r->receipt_type,
			'receipt_org'    => $r->receipt_org,
			'gdrive_url'     => $r->gdrive_url,
			'employee'       => $r->botUser
				? trim($r->botUser->firstname . ' ' . $r->botUser->lastname)
				: null,
		];
	}
}
