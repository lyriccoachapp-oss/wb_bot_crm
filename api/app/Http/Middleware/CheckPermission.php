<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Middleware проверки прав доступа (RBAC)
 *
 * Использование в routes: middleware('permission:objects.manage')
 */
class CheckPermission
{
	/**
	 * Обработать запрос
	 *
	 * @param  Request $request
	 * @param  Closure $next
	 * @param  string  $permission  Slug права доступа
	 * @return Response
	 */
	public function handle(Request $request, Closure $next, string $permission): Response
	{
		$user = JWTAuth::parseToken()->authenticate();

		if (!$user) {
			return response()->json([
				'success' => false,
				'error'   => 'Не авторизован.',
			], 401);
		}

		if (!$user->hasPermission($permission)) {
			return response()->json([
				'success' => false,
				'error'   => 'Недостаточно прав доступа.',
			], 403);
		}

		return $next($request);
	}
}
