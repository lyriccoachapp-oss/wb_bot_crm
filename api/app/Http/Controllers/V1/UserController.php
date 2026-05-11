<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Repositories\BotUserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Models\BotUser;

/**
 * Контроллер пользователей
 *
 * Просмотр пользователей из bot_users + управление CRM-пользователями.
 */
class UserController extends Controller
{
	public function __construct(
		private readonly BotUserRepository $userRepo
	) {
	}

	/**
	 * GET /api/v1/users
	 *
	 * Список пользователей (bot_users) с пагинацией.
	 */
	public function index(Request $request): JsonResponse
	{
		$page  = (int)$request->query('page',  1);
		$limit = (int)$request->query('limit', 20);

		$filters = [];
		if ($request->has('status')) {
			$filters['status'] = $request->query('status');
		}
		if ($request->has('company_slug')) {
			$filters['company_slug'] = $request->query('company_slug');
		}
		if ($request->has('search')) {
			$filters['search'] = $request->query('search');
		}

		$sort = [];
		if ($request->has('sort_by')) {
			$sort['by'] = $request->query('sort_by');
			$sort['dir'] = $request->query('sort_dir', 'asc');
		}

		$paginator = $this->userRepo->paginate($page, $limit, $filters, $sort);

		return $this->paginated($paginator, fn ($u) => [
			'id'          => $u->id,
			'id_telegram' => $u->id_telegram,
			'username'    => $u->username,
			'firstname'   => $u->firstname,
			'lastname'    => $u->lastname,
			'full_name'   => $u->full_name,
			'email'       => $u->email,
			'phone'       => $u->phone,
			'status'      => $u->status,
			'lcode'       => $u->lcode,
			'sin_num'     => $u->sin_num,
			'addr'        => $u->addr,
			'note'        => $u->note,
			'admin'       => (bool)$u->admin,
			'role_id'     => $u->role_id,
			'role_name'   => $u->role ? $u->role->name : null,
			'company_slug'=> $u->company_slug,
			'company'     => $u->company ? $u->company->name : null,
			'registered'  => $u->isRegistered(),
		]);
	}

	/**
	 * GET /api/v1/users/{id}
	 *
	 * Получить пользователя по ID (id поля БД, а не id_telegram).
	 */
	public function show(int $id): JsonResponse
	{
		$botUser = $this->userRepo->findById($id);

		if (!$botUser) {
			return $this->error('Пользователь не найден.', 404);
		}

		return $this->success([
			'id'          => $botUser->id,
			'id_telegram' => $botUser->id_telegram,
			'username'    => $botUser->username,
			'firstname'   => $botUser->firstname,
			'lastname'    => $botUser->lastname,
			'full_name'   => $botUser->full_name,
			'email'       => $botUser->email,
			'phone'       => $botUser->phone,
			'addr'        => $botUser->addr,
			'sin_num'     => $botUser->sin_num,
			'status'      => $botUser->status,
			'admin'       => (bool)$botUser->admin,
			'tester'      => $botUser->tester,
			'role_id'     => $botUser->role_id,
			'role_slug'   => $botUser->role?->slug,
			'company_slug'=> $botUser->company_slug,
			'company'     => $botUser->company ? $botUser->company->name : null,
		]);
	}

	/**
	 * POST /api/v1/users
	 *
	 * Создать пользователя.
	 */
	public function store(Request $request): JsonResponse
	{
		$validator = Validator::make($request->all(), [
			'email'    => 'required|email|unique:bot_users,email',
			'password' => 'required|string|min:6',
			'firstname'=> 'required|string',
			'lastname' => 'required|string',
			'role_id'  => 'nullable|integer|exists:crm_roles,id',
			'company_slug' => 'nullable|string|exists:companies,slug',
		]);

		if ($validator->fails()) {
			return $this->error($validator->errors()->first(), 422);
		}

		$user = BotUser::create([
			'email'    => $request->input('email'),
			'password' => Hash::make($request->input('password')),
			'firstname'=> $request->input('firstname'),
			'lastname' => $request->input('lastname'),
			'role_id'  => $request->input('role_id', null),
			'company_slug' => $request->input('company_slug', null),
			'status'   => 'new',
			'date_add' => date('Y-m-d H:i:s'),
		]);

		return $this->success([
			'id'    => $user->id,
			'email' => $user->email,
			'role'  => $user->role?->slug,
		], 'Пользователь создан.', 201);
	}

	/**
	 * PUT /api/v1/users/{id}
	 *
	 * Обновить пользователя.
	 */
	public function update(Request $request, int $id): JsonResponse
	{
		$user = $this->userRepo->findById($id);

		if (!$user) {
			return $this->error('Пользователь не найден.', 404);
		}

		$validator = Validator::make($request->all(), [
			'role_id'  => 'nullable|integer|exists:crm_roles,id',
			'status'   => 'nullable|string',
			'password' => 'nullable|string|min:6',
			'firstname'=> 'nullable|string',
			'lastname' => 'nullable|string',
			'email'    => 'nullable|email|unique:bot_users,email,' . $id,
			'phone'    => 'nullable|string',
			'username' => 'nullable|string',
			'lcode'    => 'nullable|in:ru,uk,en',
			'sin_num'  => 'nullable|string',
			'addr'     => 'nullable|string',
			'note'     => 'nullable|string',
			'company_slug' => 'nullable|string|exists:companies,slug',
		]);

		if ($validator->fails()) {
			return $this->error($validator->errors()->first(), 422);
		}

		$data = array_filter([
			'role_id'  => $request->has('role_id') ? $request->input('role_id') : null,
			'status'   => $request->input('status'),
			'password' => $request->input('password') ? Hash::make($request->input('password')) : null,
			'firstname'=> $request->input('firstname'),
			'lastname' => $request->input('lastname'),
			'email'    => $request->input('email'),
			'phone'    => $request->input('phone'),
			'username' => $request->input('username'),
			'lcode'    => $request->input('lcode'),
			'sin_num'  => $request->input('sin_num'),
			'addr'     => $request->input('addr'),
			'note'     => $request->input('note'),
			'company_slug' => $request->has('company_slug') ? $request->input('company_slug') : null,
		], fn ($v) => $v !== null && $v !== '');
		
		if ($request->has('role_id') && !$request->input('role_id')) {
			$data['role_id'] = null; // Позволяем снять роль
		}
		if ($request->has('company_slug') && !$request->input('company_slug')) {
			$data['company_slug'] = null;
		}

		$updated = $this->userRepo->update($user, $data);

		return $this->success([
			'id'     => $updated->id,
			'email'  => $updated->email,
			'role'   => $updated->role?->slug,
			'status' => $updated->status,
		], 'Пользователь обновлён.');
	}
}
