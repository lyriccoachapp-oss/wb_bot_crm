<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth as FacadesJWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

/**
 * Middleware JWT авторизации
 *
 * Проверяет наличие и валидность JWT токена в заголовке Authorization.
 */
class JwtAuth
{
	/**
	 * Обработать запрос
	 *
	 * @param  Request $request
	 * @param  Closure $next
	 * @return Response
	 */
	public function handle(Request $request, Closure $next): Response
	{
		// 1. Проверяем, является ли это запросом от Telegram Бота
		if ($request->hasHeader('X-Bot-Token')) {
			$botToken = config('services.telegram.token', env('TELEGRAM_BOT_TOKEN'));
			$legacyToken = '6263408125:AAGcrleG66uQKK_GWfmeoHyIPGbhJQFaJW4';
			
			if ($request->header('X-Bot-Token') !== $botToken && $request->header('X-Bot-Token') !== $legacyToken) {
				return response()->json(['success' => false, 'error' => 'Invalid Bot Token'], 401);
			}

			$telegramId = $request->header('X-Telegram-Id');
			if (!$telegramId) {
				return response()->json(['success' => false, 'error' => 'Missing X-Telegram-Id'], 401);
			}

			// Найти пользователя по telegram_id
			$user = \App\Models\BotUser::where('id_telegram', $telegramId)->first();

			if (!$user || $user->deleted || !$user->isRegistered()) {
				return response()->json(['success' => false, 'error' => 'Пользователь не найден или заблокирован.'], 401);
			}

			// Прокидываем пользователя дальше по цепочке
			$request->merge(['auth_user' => $user]);
			
			return $next($request);
		}

		// 2. Иначе стандартная JWT авторизация
		try {
			$user = FacadesJWTAuth::parseToken()->authenticate();

			if (!$user || $user->deleted || !$user->isRegistered()) {
				return response()->json([
					'success' => false,
					'error'   => 'Пользователь не найден или заблокирован.',
				], 401);
			}

			$request->merge(['auth_user' => $user]);

		} catch (TokenExpiredException) {
			return response()->json([
				'success' => false,
				'error'   => 'Токен истёк. Обновите токен.',
			], 401);

		} catch (JWTException) {
			return response()->json([
				'success' => false,
				'error'   => 'Токен недействителен или не передан.',
			], 401);
		}

		return $next($request);
	}
}
