<?php

return [

	/*
	|--------------------------------------------------------------------------
	| Сторонние сервисы
	|--------------------------------------------------------------------------
	*/

	// Telegram Bot
	'telegram' => [
		'token' => env('TELEGRAM_BOT_TOKEN'),
	],

	// OpenAI для GPT
	'openai' => [
		'key'   => env('OPENAI_API_KEY'),
		'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
	],

	// Google Drive (Service Account)
	'gdrive' => [
		'credentials' => env('GOOGLE_APPLICATION_CREDENTIALS'),
		'root_dir'    => env('GDRIVE_ROOT_DIR',    '11LY4saGKaA_1tW5hYsnoxQh2O0iGuDNa'),
		'receipt_dir' => env('GDRIVE_RECEIPT_DIR', '1LpqMO8W8jmw4Mj8BGLovmaxPyx9GQ_Zz'),
	],

	// Локальный OCR сервис
	'ocr' => [
		'endpoint' => env('OCR_ENDPOINT', 'http://localhost:8868/ocr'),
		'timeout'  => env('OCR_TIMEOUT', 180),
	],

	// Postmark (не используется)
	'postmark' => [
		'token' => env('POSTMARK_TOKEN'),
	],

	'ses' => [
		'key'    => env('AWS_ACCESS_KEY_ID'),
		'secret' => env('AWS_SECRET_ACCESS_KEY'),
		'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
	],

];
