<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Базовый контроллер
 *
 * Предоставляет унифицированный формат ответов API:
 * {"success": true, "data": {}, "error": null}
 */
abstract class Controller
{
	/**
	 * Успешный ответ
	 *
	 * @param  mixed       $data
	 * @param  string|null $message
	 * @param  int         $status
	 * @return JsonResponse
	 */
	protected function success(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
	{
		return response()->json([
			'success' => true,
			'data'    => $data,
			'message' => $message,
			'error'   => null,
		], $status);
	}

	/**
	 * Ответ с ошибкой
	 *
	 * @param  string $message
	 * @param  int    $status
	 * @param  mixed  $data
	 * @return JsonResponse
	 */
	protected function error(string $message, int $status = 400, mixed $data = null): JsonResponse
	{
		return response()->json([
			'success' => false,
			'data'    => $data,
			'message' => null,
			'error'   => $message,
		], $status);
	}

	/**
	 * Форматировать пагинированный ответ
	 *
	 * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator
	 * @param  callable|null $transform  Функция трансформации каждого элемента
	 * @return JsonResponse
	 */
	protected function paginated(mixed $paginator, ?callable $transform = null): JsonResponse
	{
		$items = $transform
			? $paginator->getCollection()->map($transform)->values()
			: $paginator->getCollection();

		return $this->success([
			'items'        => $items,
			'total'        => $paginator->total(),
			'per_page'     => $paginator->perPage(),
			'current_page' => $paginator->currentPage(),
			'last_page'    => $paginator->lastPage(),
		]);
	}
}
