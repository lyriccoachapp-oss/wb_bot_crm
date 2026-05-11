<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\JwtAuth;
use App\Http\Middleware\CheckPermission;

/**
 * Конфигурация Laravel приложения
 */
return Application::configure(basePath: dirname(__DIR__))
	->withRouting(
		web:      __DIR__ . '/../routes/web.php',
		api:      __DIR__ . '/../routes/api.php',
		commands: __DIR__ . '/../routes/console.php',
		health:   '/up',
		apiPrefix: 'api',
	)
	->withMiddleware(function (Middleware $middleware) {
		// Регистрируем middleware псевдонимы
		$middleware->alias([
			'api.auth'   => JwtAuth::class,
			'permission' => CheckPermission::class,
		]);
	})
	->withExceptions(function (Exceptions $exceptions) {
		// Все ошибки API возвращаем в JSON
		$exceptions->renderable(function (\Throwable $e, $request) {
			if ($request->is('api/*') || $request->expectsJson()) {
				$status  = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
				$message = $e->getMessage() ?: 'Внутренняя ошибка сервера.';

				return response()->json([
					'success' => false,
					'data'    => null,
					'message' => null,
					'error'   => $message,
				], $status);
			}
		});
	})
	->create();
