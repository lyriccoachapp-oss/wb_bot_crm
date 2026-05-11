<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Repositories\BotUserRepository;
use App\Repositories\BotPlaceRepository;
use App\Models\Company;
use Illuminate\Http\JsonResponse;

/**
 * Контроллер справочников
 *
 * Возвращает списки сотрудников и объектов для OCR-интерфейса и бота.
 */
class ReferenceController extends Controller
{
	public function __construct(
		private readonly BotUserRepository  $userRepo,
		private readonly BotPlaceRepository $placeRepo
	) {
	}

	/**
	 * GET /api/v1/references/employees
	 *
	 * Список зарегистрированных сотрудников.
	 * Совместим с форматом старых emplist.php.
	 */
	public function employees(): JsonResponse
	{
		$employees = $this->userRepo->getEmployeesList();

		return response()->json($employees);
	}

	/**
	 * GET /api/v1/references/objects
	 *
	 * Список активных объектов.
	 * Совместим с форматом старых objlist.php.
	 */
	public function objects(): JsonResponse
	{
		$objects = $this->placeRepo->getObjectsList();

		return response()->json($objects);
	}

	/**
	 * GET /api/v1/references/companies
	 *
	 * Список компаний.
	 */
	public function companies(): JsonResponse
	{
		$companies = Company::orderBy('id', 'asc')->get(['id', 'name', 'slug']);
		return response()->json($companies);
	}

	/**
	 * GET /api/v1/health
	 *
	 * Проверка работоспособности API.
	 */
	public function health(): JsonResponse
	{
		return $this->success([
			'status'    => 'ok',
			'version'   => 'v1',
			'timestamp' => now()->toIso8601String(),
		]);
	}
}
