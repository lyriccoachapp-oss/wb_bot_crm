<?php

use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\UserController;
use App\Http\Controllers\V1\ObjectController;
use App\Http\Controllers\V1\TimeEntryController;
use App\Http\Controllers\V1\ReceiptController;
use App\Http\Controllers\V1\ReportController;
use App\Http\Controllers\V1\ReferenceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Маршруты API v1
|--------------------------------------------------------------------------
|
| Все маршруты защищены JWT (кроме auth и health).
| Права доступа проверяются через middleware 'permission:slug'.
|
*/

// Публичные маршруты (без авторизации)
Route::prefix('v1')->group(function () {

	// Проверка работоспособности
	Route::get('/health', [ReferenceController::class, 'health']);

    Route::get('/test-headers', function () {
        return request()->headers->all();
    });

	// Авторизация
	Route::prefix('auth')->group(function () {
		Route::post('/login',          [AuthController::class, 'login']);
		Route::post('/telegram',       [AuthController::class, 'loginTelegram']);
		Route::post('/refresh',        [AuthController::class, 'refresh']);
		Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
		Route::post('/reset-password',  [AuthController::class, 'resetPassword']);
	});

	// Контент (публичные тексты)
	Route::get('/content', [\App\Http\Controllers\V1\ContentBlockController::class, 'index']);

	// Регистрация бота (защищены только X-Bot-Token через BotTokenMiddleware, или проверяется в коде)
	// (Регистрация перенесена в web-админку, роуты бота удалены)
});

// Защищённые маршруты (требуют JWT)
Route::prefix('v1')->middleware('api.auth')->group(function () {

	// Авторизация (с токеном)
	Route::prefix('auth')->group(function () {
		Route::get('/me',      [App\Http\Controllers\V1\AuthController::class, 'me']);
		Route::put('/profile', [App\Http\Controllers\V1\AuthController::class, 'updateProfile']);
		Route::post('/logout', [App\Http\Controllers\V1\AuthController::class, 'logout']);
	});

    // Состояние телеграм-бота
    Route::match(['get', 'post'], 'bot/operation', [App\Http\Controllers\V1\BotController::class, 'operation']);

    // Геоданные
    Route::get('locations', [App\Http\Controllers\V1\LocationController::class, 'index']);

	// Пользователи (только roles.manage или users.view)
	Route::prefix('users')->middleware('permission:users.view')->group(function () {
		Route::get('/',    [UserController::class, 'index']);
		Route::get('/{id}', [UserController::class, 'show']);
		Route::post('/', [UserController::class, 'store'])->middleware('permission:users.manage');
		Route::put('/{id}', [UserController::class, 'update'])->middleware('permission:users.manage');
	});

	// Роли
	Route::prefix('roles')->middleware('permission:roles.manage')->group(function () {
		Route::get('/',            [\App\Http\Controllers\V1\RoleController::class, 'index']);
		Route::get('/permissions', [\App\Http\Controllers\V1\RoleController::class, 'permissions']);

		Route::get('/{id}',        [\App\Http\Controllers\V1\RoleController::class, 'show']);
		Route::post('/',           [\App\Http\Controllers\V1\RoleController::class, 'store']);
		Route::put('/{id}',        [\App\Http\Controllers\V1\RoleController::class, 'update']);
		Route::delete('/{id}',     [\App\Http\Controllers\V1\RoleController::class, 'destroy']);
	});

	// Компании
	Route::prefix('companies')->middleware('permission:roles.manage')->group(function () {
		Route::get('/',            [\App\Http\Controllers\V1\CompanyController::class, 'index']);
		Route::post('/',           [\App\Http\Controllers\V1\CompanyController::class, 'store']);
		Route::put('/{id}',        [\App\Http\Controllers\V1\CompanyController::class, 'update']);
		Route::delete('/{id}',     [\App\Http\Controllers\V1\CompanyController::class, 'destroy']);
	});

	// Чеки
	Route::get('receipts/queue', [App\Http\Controllers\V1\ReceiptQueueController::class, 'index']);
	Route::post('receipts/queue', [App\Http\Controllers\V1\ReceiptQueueController::class, 'enqueue']);
	Route::get('receipts/queue/{id}/image', [App\Http\Controllers\V1\ReceiptQueueController::class, 'image']);
	Route::post('receipts/queue/{id}/save', [App\Http\Controllers\V1\ReceiptQueueController::class, 'save']);
	Route::delete('receipts/queue/{id}', [App\Http\Controllers\V1\ReceiptQueueController::class, 'destroy']);

	// Чеки (старые роуты, если нужны)
	Route::post('receipts/recognize', [App\Http\Controllers\V1\ReceiptController::class, 'recognize']);
	Route::post('receipts/upload', [App\Http\Controllers\V1\ReceiptController::class, 'upload']);
	Route::put('receipts/{id}', [App\Http\Controllers\V1\ReceiptController::class, 'update']);

	// Объекты
	Route::prefix('objects')->middleware('permission:objects.view')->group(function () {
		Route::get('/',     [ObjectController::class, 'index']);
		Route::get('/{id}', [ObjectController::class, 'show']);
		Route::post('/', [ObjectController::class, 'store'])->middleware('permission:objects.manage');
		Route::put('/{id}', [ObjectController::class, 'update'])->middleware('permission:objects.edit');
		Route::delete('/{id}', [ObjectController::class, 'destroy'])->middleware('permission:objects.edit');
		Route::patch('/{id}/toggle', [ObjectController::class, 'toggle'])->middleware('permission:objects.edit');
	});

	// Учёт рабочего времени
	Route::prefix('time-entries')->group(function () {
		Route::get('/',    [TimeEntryController::class, 'index']);
		Route::post('/',   [TimeEntryController::class, 'store'])->middleware('permission:reports.view');
		Route::put('/{id}',[TimeEntryController::class, 'update'])->middleware('permission:reports.view');
		Route::post('/check-in',  [TimeEntryController::class, 'checkIn']);
		Route::post('/check-out', [TimeEntryController::class, 'checkOut']);
		Route::post('/lunch-in',  [TimeEntryController::class, 'lunchIn']);
		Route::post('/lunch-out', [TimeEntryController::class, 'lunchOut']);
		Route::post('/{id}/location', [TimeEntryController::class, 'saveLocation']);
	});

	// Чеки
	Route::prefix('receipts')->group(function () {
		Route::get('/',     [ReceiptController::class, 'index']);
		Route::get('/{id}', [ReceiptController::class, 'show']);
		Route::post('/',    [ReceiptController::class, 'store']);
		Route::post('/recognize', [ReceiptController::class, 'recognize']);
		Route::post('/upload', [ReceiptController::class, 'upload']);
		Route::put('/{id}', [ReceiptController::class, 'update'])->middleware('permission:receipts.manage');
	});

	// Отчёты (только admin)
	Route::prefix('reports')->middleware('permission:reports.view')->group(function () {
		Route::get('/objects',      [ReportController::class, 'objects']);
		Route::get('/objects/xlsx', [ReportController::class, 'objectsXlsx']);
		Route::get('/employees',      [ReportController::class, 'employees']);
		Route::get('/employees/xlsx', [ReportController::class, 'employeesXlsx']);
	});

	// Справочники (без ограничений — нужны боту и OCR)
	Route::prefix('references')->group(function () {
		Route::get('/employees', [ReferenceController::class, 'employees']);
		Route::get('/objects',   [ReferenceController::class, 'objects']);
		Route::get('/companies', [ReferenceController::class, 'companies']);
	});

	// Контент (управление для админов)
	Route::prefix('content')->middleware('permission:roles.manage')->group(function () {
		Route::post('/', [\App\Http\Controllers\V1\ContentBlockController::class, 'store']);
		Route::put('/{id}', [\App\Http\Controllers\V1\ContentBlockController::class, 'update']);
		Route::delete('/{id}', [\App\Http\Controllers\V1\ContentBlockController::class, 'destroy']);
	});

});
