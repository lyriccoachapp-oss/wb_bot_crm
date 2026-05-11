<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RoleController extends Controller
{
	/**
	 * GET /api/v1/roles
	 */
	public function index(): JsonResponse
	{
		$roles = Role::orderBy('id', 'asc')->get();
		return $this->success($roles);
	}

	/**
	 * GET /api/v1/roles/permissions
	 * Список доступных прав
	 */
	public function permissions(): JsonResponse
	{
		// Хардкод всех возможных прав в системе
		$permissions = [
			'users.view' => 'Просмотр пользователей',
			'users.manage' => 'Управление пользователями',
			'roles.manage' => 'Управление ролями',
			'objects.view' => 'Просмотр объектов',
			'objects.manage' => 'Создание объектов',
			'objects.edit' => 'Редактирование объектов',
			'time_entries.manage' => 'Управление рабочим временем',
			'receipts.view_all' => 'Просмотр всех чеков',
			'receipts.manage' => 'Управление чеками',
			'reports.view' => 'Просмотр отчетов',
		];

		return $this->success($permissions);
	}

	/**
	 * POST /api/v1/roles
	 */
	public function store(Request $request): JsonResponse
	{
		$validator = Validator::make($request->all(), [
			'name' => 'required|string|max:255',
			'permissions' => 'array',
		]);

		if ($validator->fails()) {
			return $this->error($validator->errors()->first(), 422);
		}

		$role = Role::create([
			'name' => $request->input('name'),
			'slug' => Str::slug($request->input('name')),
			'permissions' => $request->input('permissions', [])
		]);

		// Если slug совпал или пуст (например, не латиница), можно сгенерировать
		if (empty($role->slug)) {
			$role->slug = 'role-' . $role->id;
			$role->save();
		}

		return $this->success($role, 'Роль создана успешно', 201);
	}

	/**
	 * GET /api/v1/roles/{id}
	 */
	public function show($id): JsonResponse
	{
		$role = Role::find($id);
		if (!$role) {
			return $this->error('Роль не найдена', 404);
		}

		return $this->success($role);
	}

	/**
	 * PUT /api/v1/roles/{id}
	 */
	public function update(Request $request, $id): JsonResponse
	{
		$role = Role::find($id);
		if (!$role) {
			return $this->error('Роль не найдена', 404);
		}

		$validator = Validator::make($request->all(), [
			'name' => 'required|string|max:255',
			'permissions' => 'array',
		]);

		if ($validator->fails()) {
			return $this->error($validator->errors()->first(), 422);
		}

		// Запрещаем переименование slug для admin и user, чтобы не сломать логику
		if (!in_array($role->slug, ['admin', 'user'])) {
			$role->slug = Str::slug($request->input('name'));
			if (empty($role->slug)) {
				$role->slug = 'role-' . $role->id;
			}
		}

		$role->name = $request->input('name');
		$role->permissions = $request->input('permissions', []);
		$role->save();

		return $this->success($role, 'Роль обновлена успешно');
	}

	/**
	 * DELETE /api/v1/roles/{id}
	 */
	public function destroy($id): JsonResponse
	{
		$role = Role::find($id);
		if (!$role) {
			return $this->error('Роль не найдена', 404);
		}

		if (in_array($role->slug, ['admin', 'user'])) {
			return $this->error('Нельзя удалить системную роль', 403);
		}

		$role->delete();

		return $this->success(null, 'Роль удалена');
	}
}
