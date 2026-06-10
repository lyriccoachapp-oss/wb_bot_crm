<?php

namespace App\Repositories;

use App\Models\BotPlace;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Repository для объектов (рабочих площадок)
 *
 * Читает и записывает в таблицу bot_places.
 */
class BotPlaceRepository
{
	/**
	 * Получить все объекты с пагинацией
	 */
	public function paginate(int $page = 1, int $limit = 20, array $filters = [], array $sort = []): LengthAwarePaginator
	{
		$query = BotPlace::query();

		if (isset($filters['active']) && $filters['active'] !== '') {
			$query->where('active', (int)$filters['active']);
		}

		if (!empty($filters['search'])) {
			$s = '%' . $filters['search'] . '%';
			$query->where('place_name', 'LIKE', $s);
		}

		if (!empty($sort['by'])) {
			$dir = (!empty($sort['dir']) && strtolower($sort['dir']) === 'desc') ? 'desc' : 'asc';
			$col = $sort['by'] === 'id' ? 'id_place' : $sort['by'];
			$query->orderBy($col, $dir);
		} else {
			$query->orderBy('place_name');
		}

		return $query->paginate($limit, ['*'], 'page', $page);
	}

	/**
	 * Получить все отфильтрованные объекты (для экспорта)
	 */
	public function all(array $filters = [], array $sort = []): \Illuminate\Support\Collection
	{
		$query = BotPlace::query();

		if (isset($filters['active']) && $filters['active'] !== '') {
			$query->where('active', (int)$filters['active']);
		}

		if (!empty($filters['search'])) {
			$s = '%' . $filters['search'] . '%';
			$query->where('place_name', 'LIKE', $s);
		}

		if (!empty($sort['by'])) {
			$dir = (!empty($sort['dir']) && strtolower($sort['dir']) === 'desc') ? 'desc' : 'asc';
			$col = $sort['by'] === 'id' ? 'id_place' : $sort['by'];
			$query->orderBy($col, $dir);
		} else {
			$query->orderBy('place_name');
		}

		return $query->get();
	}

	/**
	 * Найти объект по ID
	 */
	public function findById(int $id): ?BotPlace
	{
		return BotPlace::find($id);
	}

	/**
	 * Создать объект
	 *
	 * @param  array $data
	 * @return BotPlace
	 */
	public function create(array $data): BotPlace
	{
		return BotPlace::create($data);
	}

	/**
	 * Обновить объект
	 *
	 * @param  BotPlace $place
	 * @param  array    $data
	 * @return BotPlace
	 */
	public function update(BotPlace $place, array $data): BotPlace
	{
		$place->update($data);

		return $place->fresh();
	}

	/**
	 * Удалить объект
	 */
	public function delete(BotPlace $place): bool
	{
		return $place->delete();
	}

	/**
	 * Переключить статус активности объекта
	 */
	public function toggle(BotPlace $place): BotPlace
	{
		$place->update(['active' => !$place->active]);

		return $place->fresh();
	}

	/**
	 * Получить список объектов для справочника (OCR, бот)
	 *
	 * @return array
	 */
	public function getObjectsList(): array
	{
		return BotPlace::where('active', 1)
			->orderBy('place_name')
			->get(['id_place', 'place_name'])
			->map(fn ($p) => [
				'id'   => $p->id_place,
				'name' => $p->place_name,
			])
			->toArray();
	}
}
