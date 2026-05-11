<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Services\TimeEntryService;
use App\Repositories\BotWorktimeRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Контроллер учёта рабочего времени
 *
 * Check-in, check-out, обед, геолокация, просмотр.
 */
class TimeEntryController extends Controller
{
	public function __construct(
		private readonly TimeEntryService      $timeService,
		private readonly BotWorktimeRepository $worktimeRepo
	) {
	}

	/**
	 * GET /api/v1/time-entries
	 *
	 * Список записей рабочего времени.
	 */
	public function index(Request $request): JsonResponse
	{
		$user  = $request->auth_user;
		$page  = (int)$request->query('page',  1);
		$limit = (int)$request->query('limit', 20);

		$filters = [];

		if (!$user->isAdmin()) {
			$filters['telegram_id'] = $user->id_telegram;
		} elseif ($request->has('telegram_id')) {
			$val = $request->query('telegram_id');
			$filters['telegram_id'] = is_string($val) && str_contains($val, ',') ? explode(',', $val) : $val;
		}

		if ($request->has('place_id')) {
			$val = $request->query('place_id');
			$filters['place_id'] = is_string($val) && str_contains($val, ',') ? explode(',', $val) : $val;
		}
		if ($request->has('date_from')) $filters['date_from'] = $request->query('date_from');
		if ($request->has('date_to'))   $filters['date_to']   = $request->query('date_to');
		if ($request->has('include_open')) $filters['include_open'] = filter_var($request->query('include_open'), FILTER_VALIDATE_BOOLEAN);

		$paginator = $this->worktimeRepo->paginate($filters, $page, $limit);

		return $this->paginated($paginator, fn ($w) => $this->formatEntry($w));
	}

	/**
	 * POST /api/v1/time-entries/check-in
	 *
	 * Начать рабочий день.
	 */
	public function checkIn(Request $request): JsonResponse
	{
		$user = $request->auth_user;

		$validator = Validator::make($request->all(), [
			'place_id'  => 'nullable|integer',
			'latitude'  => 'nullable|numeric|between:-90,90',
			'longitude' => 'nullable|numeric|between:-180,180',
		]);

		if ($validator->fails()) {
			return $this->error($validator->errors()->first(), 422);
		}

		if (!$user->id_telegram) {
			return $this->error('Telegram ID не привязан к аккаунту.', 422);
		}

		try {
			$entry = $this->timeService->checkIn($user->id_telegram, $request->only([
				'place_id', 'latitude', 'longitude',
			]));

			return $this->success($this->formatEntry($entry), 'Check-in выполнен.', 201);
		} catch (\RuntimeException $e) {
			return $this->error($e->getMessage(), 409);
		}
	}

	/**
	 * POST /api/v1/time-entries/check-out
	 *
	 * Завершить рабочий день.
	 */
	public function checkOut(Request $request): JsonResponse
	{
		$user = $request->auth_user;

		$validator = Validator::make($request->all(), [
			'id'        => 'nullable|integer',
			'work_desc' => 'nullable|string|max:1000',
		]);

		if ($validator->fails()) {
			return $this->error($validator->errors()->first(), 422);
		}

		if (!$user->id_telegram) {
			return $this->error('Telegram ID не привязан к аккаунту.', 422);
		}

		try {
			$entry = $this->timeService->checkOut($user->id_telegram, $request->only(['id', 'work_desc', 'latitude', 'longitude']));

			return $this->success($this->formatEntry($entry), 'Check-out выполнен.');
		} catch (\RuntimeException $e) {
			return $this->error($e->getMessage(), 409);
		}
	}

	/**
	 * POST /api/v1/time-entries/lunch-in
	 *
	 * Начать обед.
	 */
	public function lunchIn(Request $request): JsonResponse
	{
		$user = $request->auth_user;

		if (!$user->id_telegram) {
			return $this->error('Telegram ID не привязан к аккаунту.', 422);
		}

		try {
			$entry = $this->timeService->lunchIn($user->id_telegram);

			return $this->success($this->formatEntry($entry), 'Начало обеда зафиксировано.');
		} catch (\RuntimeException $e) {
			return $this->error($e->getMessage(), 409);
		}
	}

	/**
	 * POST /api/v1/time-entries/lunch-out
	 *
	 * Завершить обед.
	 */
	public function lunchOut(Request $request): JsonResponse
	{
		$user = $request->auth_user;

		if (!$user->id_telegram) {
			return $this->error('Telegram ID не привязан к аккаунту.', 422);
		}

		try {
			$entry = $this->timeService->lunchOut($user->id_telegram);

			return $this->success($this->formatEntry($entry), 'Конец обеда зафиксирован.');
		} catch (\RuntimeException $e) {
			return $this->error($e->getMessage(), 409);
		}
	}

	/**
	 * POST /api/v1/time-entries/{id}/location
	 *
	 * Сохранить геолокацию для записи.
	 */
	public function saveLocation(Request $request, int $id): JsonResponse
	{
		$validator = Validator::make($request->all(), [
			'latitude'  => 'required|numeric|between:-90,90',
			'longitude' => 'required|numeric|between:-180,180',
		]);

		if ($validator->fails()) {
			return $this->error($validator->errors()->first(), 422);
		}

		try {
			$entry = $this->timeService->saveLocation(
				$id,
				(float)$request->input('latitude'),
				(float)$request->input('longitude')
			);

			return $this->success($this->formatEntry($entry), 'Геолокация сохранена.');
		} catch (\RuntimeException $e) {
			return $this->error($e->getMessage(), 404);
		}
	}

	/**
	 * Форматировать запись рабочего времени
	 */
	private function formatEntry(mixed $entry): array
	{
		return [
			'id'           => $entry->id_worktime,
			'telegram_id'  => $entry->id_telegram,
			'place_id'     => $entry->id_place,
			'place_name'   => $entry->place?->place_name,
			'workday'      => $entry->workday,
			'checkin'      => $entry->checkin,
			'checkout'     => $entry->checkout,
			'lunchin'      => $entry->lunchin,
			'lunchout'     => $entry->lunchout,
			'gas_costs'    => $entry->gas_costs,
			'work_seconds' => $entry->work_seconds,
			'work_minutes_rounded' => $entry->work_minutes_rounded,
			'work_desc'    => $entry->workdone,
			'latitude'     => $entry->latitude,
			'longitude'    => $entry->longitude,
		];
	}

	/**
	 * POST /api/v1/time-entries
	 *
	 * Ручное создание смены (только admin)
	 */
	public function store(Request $request): JsonResponse
	{
		$validator = Validator::make($request->all(), [
			'telegram_id' => 'required|integer',
			'place_id'    => 'nullable|integer',
			'workday'     => 'required|date_format:Y-m-d',
			'checkin'     => 'nullable|date_format:Y-m-d H:i:s',
			'checkout'    => 'nullable|date_format:Y-m-d H:i:s',
			'lunchin'     => 'nullable|date_format:Y-m-d H:i:s',
			'lunchout'    => 'nullable|date_format:Y-m-d H:i:s',
		]);

		if ($validator->fails()) {
			return $this->error($validator->errors()->first(), 422);
		}

		$data = [
			'id_telegram' => $request->input('telegram_id'),
			'id_place'    => $request->input('place_id'),
			'workday'     => $request->input('workday'),
			'checkin'     => $request->input('checkin') ?: '0000-00-00 00:00:00',
			'checkout'    => $request->input('checkout') ?: '0000-00-00 00:00:00',
			'lunchin'     => $request->input('lunchin') ?: '0000-00-00 00:00:00',
			'lunchout'    => $request->input('lunchout') ?: '0000-00-00 00:00:00',
		];

		$entry = $this->worktimeRepo->create($data);

		return $this->success($this->formatEntry($entry), 'Смена успешно создана', 201);
	}

	/**
	 * PUT /api/v1/time-entries/{id}
	 *
	 * Редактирование смены (только admin)
	 */
	public function update(Request $request, int $id): JsonResponse
	{
		$entry = $this->worktimeRepo->findById($id);

		if (!$entry) {
			return $this->error('Смена не найдена', 404);
		}

		$validator = Validator::make($request->all(), [
			'place_id'    => 'nullable|integer',
			'checkin'     => 'nullable|date_format:Y-m-d H:i:s',
			'checkout'    => 'nullable|date_format:Y-m-d H:i:s',
			'lunchin'     => 'nullable|date_format:Y-m-d H:i:s',
			'lunchout'    => 'nullable|date_format:Y-m-d H:i:s',
		]);

		if ($validator->fails()) {
			return $this->error($validator->errors()->first(), 422);
		}

		$data = [
			'id_place'    => $request->input('place_id', $entry->id_place),
			'checkin'     => $request->input('checkin') ?: '0000-00-00 00:00:00',
			'checkout'    => $request->input('checkout') ?: '0000-00-00 00:00:00',
			'lunchin'     => $request->input('lunchin') ?: '0000-00-00 00:00:00',
			'lunchout'    => $request->input('lunchout') ?: '0000-00-00 00:00:00',
		];

		$entry = $this->worktimeRepo->update($entry, $data);

		return $this->success($this->formatEntry($entry), 'Смена успешно обновлена');
	}
}
