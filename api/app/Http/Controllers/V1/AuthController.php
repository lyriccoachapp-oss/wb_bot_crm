<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Контроллер авторизации
 *
 * Обрабатывает вход, выход, Telegram auth, refresh, сброс пароля.
 */
class AuthController extends Controller
{
	public function __construct(
		private readonly AuthService $authService
	) {
	}

	/**
	 * POST /api/v1/auth/login
	 *
	 * Авторизация по email и паролю.
	 */
	public function login(Request $request): JsonResponse
	{
		$validator = Validator::make($request->all(), [
			'email'    => 'required|email',
			'password' => 'required|string|min:6',
		]);

		if ($validator->fails()) {
			return $this->error($validator->errors()->first(), 422);
		}

		try {
			$tokens = $this->authService->login(
				$request->input('email'),
				$request->input('password')
			);

			return $this->success($tokens, 'Авторизация успешна.');
		} catch (\RuntimeException $e) {
			return $this->error($e->getMessage(), 401);
		}
	}

	/**
	 * POST /api/v1/auth/telegram
	 *
	 * Авторизация через Telegram (по telegram_id + hash).
	 */
	public function loginTelegram(Request $request): JsonResponse
	{
		$data = $request->all();

		if ($request->filled('init_data')) {
			$initDataStr = $request->input('init_data');
			parse_str($initDataStr, $parsed);
			
			$userId = null;
			if (isset($parsed['user'])) {
				$userObj = json_decode($parsed['user'], true);
				if ($userObj && isset($userObj['id'])) {
					$userId = $userObj['id'];
				}
			}

			if (!$userId || !isset($parsed['hash']) || !isset($parsed['auth_date'])) {
				return $this->error('Invalid init_data', 422);
			}

			try {
				$tokens = $this->authService->loginWithTelegram(
					(int)$userId,
					$parsed['hash'],
					$parsed
				);
				return $this->success($tokens, 'Авторизация через Telegram WebApp успешна.');
			} catch (\RuntimeException $e) {
				return $this->error($e->getMessage(), 401);
			}
		}

		$validator = Validator::make($data, [
			'id'        => 'required|integer',
			'hash'      => 'required|string',
			'auth_date' => 'required|integer',
		]);

		if ($validator->fails()) {
			return $this->error($validator->errors()->first(), 422);
		}

		try {
			$tokens = $this->authService->loginWithTelegram(
				(int)$request->input('id'),
				$request->input('hash'),
				$request->all()
			);

			return $this->success($tokens, 'Авторизация через Telegram успешна.');
		} catch (\RuntimeException $e) {
			return $this->error($e->getMessage(), 401);
		}
	}

	/**
	 * GET /api/v1/auth/me
	 *
	 * Получить данные текущего пользователя.
	 */
	public function me(Request $request): JsonResponse
	{
		$user    = $request->auth_user;

		return $this->success([
			'id'          => $user->id,
			'email'       => $user->email,
			'name'        => trim($user->firstname . ' ' . $user->lastname),
			'telegram_id' => $user->id_telegram,
			'role'        => $user->role ? $user->role->slug : ($user->isAdmin() ? 'admin' : 'user'),
			'permissions' => $user->isAdmin() ? ['all'] : ($user->role ? ($user->role->permissions ?? []) : []),
			'active'      => !$user->deleted,
			'lcode'       => $user->lcode ?? 'en',
			'reset_password_validity' => $user->reset_password_validity,
			'profile'     => [
				'firstname' => $user->firstname,
				'lastname'  => $user->lastname,
				'phone'     => $user->phone_number ?? $user->phone,
				'status'    => $user->status,
			],
		]);
	}

	/**
	 * PUT /api/v1/auth/profile
	 *
	 * Обновить профиль текущего пользователя.
	 */
	public function updateProfile(Request $request): JsonResponse
	{
		$user = $request->auth_user;
		
		$validator = Validator::make($request->all(), [
			'firstname' => 'nullable|string|max:100',
			'lastname'  => 'nullable|string|max:100',
			'email'     => 'nullable|email|unique:bot_users,email,' . $user->id,
			'phone'     => 'nullable|string|max:50',
			'password'  => 'nullable|string|min:8',
			'reset_password_validity' => 'nullable|string|max:20'
		]);

		if ($validator->fails()) {
			return $this->error($validator->errors()->first(), 422);
		}

		$data = $request->only(['firstname', 'lastname', 'email', 'phone', 'reset_password_validity']);
		
		if ($request->filled('password')) {
			$data['password'] = password_hash($request->input('password'), PASSWORD_BCRYPT);
		}

		$user->update($data);

		return $this->success(null, 'Профиль успешно обновлен.');
	}

	/**
	 * POST /api/v1/auth/refresh
	 *
	 * Обновить access-токен по refresh-токену.
	 */
	public function refresh(Request $request): JsonResponse
	{
		$validator = Validator::make($request->all(), [
			'refresh_token' => 'required|string',
		]);

		if ($validator->fails()) {
			return $this->error($validator->errors()->first(), 422);
		}

		try {
			$tokens = $this->authService->refresh($request->input('refresh_token'));

			return $this->success($tokens, 'Токен обновлён.');
		} catch (\RuntimeException $e) {
			return $this->error($e->getMessage(), 401);
		}
	}

	/**
	 * POST /api/v1/auth/logout
	 *
	 * Выйти из системы.
	 */
	public function logout(Request $request): JsonResponse
	{
		$user = $request->auth_user;
		$this->authService->logout($user);

		return $this->success(null, 'Вы вышли из системы.');
	}

	/**
	 * POST /api/v1/auth/forgot-password
	 *
	 * Запрос ссылки для сброса пароля.
	 */
	public function forgotPassword(Request $request): JsonResponse
	{
		$validator = Validator::make($request->all(), [
			'email' => 'required|email',
		]);

		if ($validator->fails()) {
			return $this->error($validator->errors()->first(), 422);
		}

		try {
			$this->authService->forgotPassword($request->input('email'));

			return $this->success(null, 'Инструкции по сбросу пароля отправлены на email.');
		} catch (\RuntimeException $e) {
			return $this->error($e->getMessage(), 500);
		}
	}

	/**
	 * POST /api/v1/auth/reset-password
	 *
	 * Установить новый пароль.
	 */
	public function resetPassword(Request $request): JsonResponse
	{
		$validator = Validator::make($request->all(), [
			'token'    => 'required|string',
			'password' => 'required|string|min:8|confirmed',
		]);

		if ($validator->fails()) {
			return $this->error($validator->errors()->first(), 422);
		}

		try {
			$this->authService->resetPassword(
				$request->input('token'),
				$request->input('password')
			);

			return $this->success(null, 'Пароль успешно изменён.');
		} catch (\RuntimeException $e) {
			return $this->error($e->getMessage(), 400);
		}
	}
}
