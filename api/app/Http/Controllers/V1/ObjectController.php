<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Repositories\BotPlaceRepository;
use App\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Контроллер объектов (рабочих площадок)
 *
 * CRUD операции над bot_places.
 * При создании/переименовании объекта — синхронизирует папку на Google Drive.
 */
class ObjectController extends Controller
{
	public function __construct(
		private readonly BotPlaceRepository  $placeRepo,
		private readonly GoogleDriveService  $driveService
	) {
	}

	/**
	 * GET /api/v1/objects
	 *
	 * Список объектов с пагинацией.
	 */
	public function index(Request $request): JsonResponse
	{
		$page  = (int)$request->query('page',  1);
		$limit = (int)$request->query('limit', 20);

		$filters = [];
		if ($request->has('active')) {
			$filters['active'] = $request->query('active');
		} elseif ($request->has('active_only') && filter_var($request->query('active_only'), FILTER_VALIDATE_BOOLEAN)) {
			$filters['active'] = 1;
		}

		if ($request->has('search')) {
			$filters['search'] = $request->query('search');
		}

		$sort = [];
		if ($request->has('sort_by')) {
			$sort['by'] = $request->query('sort_by');
			$sort['dir'] = $request->query('sort_dir', 'asc');
		}

		$paginator = $this->placeRepo->paginate($page, $limit, $filters, $sort);

		return $this->paginated($paginator, fn ($p) => $this->formatPlace($p));
	}

	/**
	 * POST /api/v1/objects
	 *
	 * Создать новый объект.
	 */
	public function store(Request $request): JsonResponse
	{
		$validator = Validator::make($request->all(), [
			'place_name'    => 'required|string|max:255',
			'place_address' => 'required|string|max:500',
			'works_type'    => 'required|string|max:255',
		]);

		if ($validator->fails()) {
			return $this->error($validator->errors()->first(), 422);
		}

		// Проверка на дубликат (по адресу и типу работ)
		$exists = \App\Models\BotPlace::where('place_address', $request->input('place_address'))
			->where('works_type', $request->input('works_type'))
			->exists();

		if ($exists) {
			return $this->error('Объект по такому адресу с таким типом работ уже существует', 422);
		}

		$place = $this->placeRepo->create([
			'active'        => true,
			'place_name'    => $request->input('place_name'),
			'place_address' => $request->input('place_address'),
			'works_type'    => $request->input('works_type'),
			'id_telegram'   => $request->auth_user->id ?? 0,
			'date_add'      => now(),
		]);

		// Создаём папку на Google Drive (как в старом боте)
		$gdriveId = $this->driveService->createFolder(
			$place->place_name,
			$place->place_address ?? ''
		);

		if ($gdriveId) {
			$this->placeRepo->update($place, ['gdrive_id' => $gdriveId]);
			$place->gdrive_id = $gdriveId;
		}

		return $this->success($this->formatPlace($place), 'Объект создан.', 201);
	}

	/**
	 * GET /api/v1/objects/{id}
	 *
	 * Получить объект по ID.
	 */
	public function show(int $id): JsonResponse
	{
		$place = $this->placeRepo->findById($id);

		if (!$place) {
			return $this->error('Объект не найден.', 404);
		}

		return $this->success($this->formatPlace($place));
	}

	/**
	 * PUT /api/v1/objects/{id}
	 *
	 * Обновить объект.
	 */
	public function update(Request $request, int $id): JsonResponse
	{
		$place = $this->placeRepo->findById($id);

		if (!$place) {
			return $this->error('Объект не найден.', 404);
		}

		$validator = Validator::make($request->all(), [
			'place_name'    => 'sometimes|required|string|max:255',
			'place_address' => 'nullable|string|max:500',
			'works_type'    => 'nullable|string|max:255',
			'active'        => 'sometimes|boolean',
		]);

		if ($validator->fails()) {
			return $this->error($validator->errors()->first(), 422);
		}

		$updated = $this->placeRepo->update($place, $request->only(['place_name', 'place_address', 'works_type', 'active']));

		// Синхронизируем папку на Google Drive
		if ($updated->gdrive_id) {
			// Папка существует — переименовываем
			$this->driveService->renameFolder(
				$updated->gdrive_id,
				$updated->place_name,
				$updated->place_address ?? ''
			);
		} elseif ($request->filled('place_name')) {
			// Папки нет — создаём
			$gdriveId = $this->driveService->createFolder(
				$updated->place_name,
				$updated->place_address ?? ''
			);
			if ($gdriveId) {
				$this->placeRepo->update($updated, ['gdrive_id' => $gdriveId]);
				$updated->gdrive_id = $gdriveId;
			}
		}

		return $this->success($this->formatPlace($updated), 'Объект обновлён.');
	}

	/**
	 * DELETE /api/v1/objects/{id}
	 *
	 * Удалить объект.
	 */
	public function destroy(int $id): JsonResponse
	{
		$place = $this->placeRepo->findById($id);

		if (!$place) {
			return $this->error('Объект не найден.', 404);
		}

		$this->placeRepo->delete($place);

		return $this->success(null, 'Объект удалён.');
	}

	/**
	 * PATCH /api/v1/objects/{id}/toggle
	 *
	 * Переключить активность объекта.
	 */
	public function toggle(int $id): JsonResponse
	{
		$place = $this->placeRepo->findById($id);

		if (!$place) {
			return $this->error('Объект не найден.', 404);
		}

		$updated = $this->placeRepo->toggle($place);

		return $this->success(
			$this->formatPlace($updated),
			$updated->active ? 'Объект активирован.' : 'Объект деактивирован.'
		);
	}

	/**
	 * Форматировать объект для ответа
	 *
	 * @param  \App\Models\BotPlace $place
	 * @return array
	 */
	private function formatPlace(mixed $place): array
	{
		return [
			'id'         => $place->id_place,
			'name'       => $place->place_name,
			'address'    => $place->place_address,
			'works_type' => $place->works_type,
			'active'     => (bool)$place->active,
			'gdrive_id'  => $place->gdrive_id ?: null,
			'gdrive_url' => $place->gdrive_id
				? $this->driveService->getFileUrl($place->gdrive_id)
				: null,
		];
	}
}
