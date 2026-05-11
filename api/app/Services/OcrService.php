<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Сервис OCR + GPT для чеков
 *
 * Отправляет изображение в локальный OCR-сервис,
 * затем парсит текст через OpenAI GPT-4o-mini.
 */
class OcrService
{
	/**
	 * Распознать чек из файла
	 *
	 * @param  string $filePath  Абсолютный путь к изображению
	 * @return array             Структурированные данные чека
	 *
	 * @throws RuntimeException
	 */
	public function recognizeReceipt(string $filePath, ?string $fileLastModifiedJs = null): array
	{
		$nowDate = date('Y-m-d H:i:s');
		$fileModDate = 'Unknown';
		if ($fileLastModifiedJs && is_numeric($fileLastModifiedJs)) {
			$fileModDate = date('Y-m-d H:i:s', (int)($fileLastModifiedJs / 1000));
		}

		$exifDate = 'Unknown';
		try {
			if (function_exists('exif_read_data') && @is_readable($filePath)) {
				$exif = @exif_read_data($filePath);
				if ($exif && isset($exif['DateTimeOriginal'])) {
					$exifDate = date('Y-m-d H:i:s', strtotime($exif['DateTimeOriginal']));
				}
			}
		} catch (\Exception $e) {}

		// 1. Извлекаем сырой текст
		$ocrText = $this->extractText($filePath);

		// 2. GPT — парсим структурированные данные
		$parsed = $this->parseWithGpt($ocrText, $nowDate, $fileModDate, $exifDate);

		// 3. Нормализуем и возвращаем данные
		return $this->normalizeFields($parsed, $ocrText);
	}

	/**
	 * Извлечь текст из изображения через локальный OCR-сервис
	 *
	 * @param  string $filePath
	 * @return string
	 *
	 * @throws RuntimeException
	 */
	private function extractText(string $filePath): string
	{
		$endpoint = config('services.ocr.endpoint');
		$timeout  = (int)config('services.ocr.timeout', 180);

		try {
			$response = Http::connectTimeout($timeout)
				->timeout($timeout)
				->attach('file', file_get_contents($filePath), basename($filePath))
				->post($endpoint);
		} catch (\Exception $e) {
			throw new RuntimeException('OCR сервис недоступен: ' . $e->getMessage());
		}

		if (!$response->successful()) {
			throw new RuntimeException('OCR ошибка HTTP ' . $response->status());
		}

		$data = $response->json();

		if (!isset($data['text'])) {
			throw new RuntimeException('OCR вернул неожиданный формат ответа.');
		}

		return (string)$data['text'];
	}

	/**
	 * Разобрать OCR-текст через GPT-4o-mini
	 *
	 * @param  string $ocrText
     * @param  string $dateHint
	 * @return array
	 *
	 * @throws RuntimeException
	 */
	private function parseWithGpt(string $ocrText, string $nowDate, string $fileModDate, string $exifDate): array
	{
		$apiKey = config('services.openai.key');
		$model  = config('services.openai.model', 'gpt-4o-mini');

		if (!str_starts_with($apiKey, 'sk-')) {
			throw new RuntimeException('OpenAI API key не настроен.');
		}

		$systemPrompt = <<<SYS
You extract structured data from Canadian retail receipts.
Return ONLY a JSON object matching the schema. No markdown.

FILE METADATA HINTS:
- Current Server Time: {$nowDate}
- File Last Modified: {$fileModDate}
- Photo Taken (EXIF): {$exifDate}

CRITICAL RULE FOR DATES: You MUST use the file metadata hints above to logically interpret the receipt's date. Receipts often have confusing date formats where the year and day are swapped (e.g. "26/04/17" could mean April 26, 2017 OR April 17, 2026). If a date is ambiguous, evaluate both interpretations and choose the one that is closest in time to the file metadata hints. The receipt date will almost never be years apart from when the photo was taken or modified. 
For example, if the file was created or modified in 2026, and the receipt says "26/04/17", you MUST interpret it as 2026-04-17, NOT 2017-04-26!

Rules:
- Do not invent data; use null if missing.
- Date: YYYY-MM-DD; Time: HH:MM 24h.
- Currency: CAD by default.
- Taxes: HST/GST/PST. Look for explicit tax lines. IF there is no separate tax line but there are lines like "HST INCLUDED IN FUEL $ X.XX", EXTRACT these amounts and SUM them for the tax field. Calculate subtotal correctly (subtotal = total - tax).
- Payment: cash vs card; detect last 4 if present.
- Category: one of ["fuel","materials","groceries","tools","restaurant","other"].
- Items: include if clearly listed. Do not output marketing slogans as merchant names.
SYS;

		$schema = [
			'receiptDate' => 'YYYY-MM-DD or null',
			'receiptTime' => 'HH:MM or null',
			'merchant'    => ['name' => 'string or null', 'address' => 'string or null'],
			'currency'    => 'CAD or 3-letter code',
			'amounts'     => ['subtotal' => 'number or null', 'tax' => 'number or null', 'total' => 'number or null'],
			'items'       => [['description' => 'string', 'qty' => 'number', 'unitPrice' => 'number or null', 'lineTotal' => 'number or null']],
			'payment'     => ['method' => 'cash|card|null', 'cardLast4' => 'string(4) or null'],
			'category'    => 'fuel|materials|groceries|tools|restaurant|other',
			'notes'       => 'string or null',
		];

		try {
			$response = Http::withToken($apiKey)
				->connectTimeout(180)
				->timeout(180)
				->post('https://api.openai.com/v1/chat/completions', [
					'model'           => $model,
					'response_format' => ['type' => 'json_object'],
					'temperature'     => 0.1,
					'messages'        => [
						['role' => 'system', 'content' => $systemPrompt],
						['role' => 'user',   'content' => "OCR_TEXT:\n{$ocrText}\n\nSCHEMA:\n" . json_encode($schema)],
					],
				]);
		} catch (\Exception $e) {
			throw new RuntimeException('OpenAI API недоступен: ' . $e->getMessage());
		}

		if (!$response->successful()) {
			throw new RuntimeException('OpenAI HTTP ' . $response->status() . ': ' . $response->body());
		}

		$content = $response->json('choices.0.message.content', '{}');
		$parsed  = json_decode($content, true);

		if (!is_array($parsed)) {
			throw new RuntimeException('GPT вернул неверный JSON.');
		}

		return $parsed;
	}

	/**
	 * Нормализовать поля для сохранения в БД
	 *
	 * @param  array  $parsed   Данные от GPT
	 * @param  string $ocrText  Исходный OCR текст
	 * @return array
	 */
	private function normalizeFields(array $parsed, string $ocrText): array
	{
		return [
			'merchant_name'    => $this->normalizeMerchantName($parsed['merchant']['name'] ?? null, $ocrText),
			'merchant_address' => $parsed['merchant']['address'] ?? null,
			'receipt_date'     => $this->normalizeDate($parsed['receiptDate'] ?? null),
			'receipt_time'     => $this->normalizeTime($parsed['receiptTime'] ?? null),
			'subtotal'         => $this->normalizeMoney($parsed['amounts']['subtotal'] ?? null),
			'tax'              => $this->normalizeMoney($parsed['amounts']['tax'] ?? null),
			'receipt_amount'   => $this->normalizeMoney($parsed['amounts']['total'] ?? null),
			'currency'         => strtoupper(substr($parsed['currency'] ?? 'CAD', 0, 3)),
			'payment_method'   => $parsed['payment']['method'] ?? null,
			'card_last4'       => $parsed['payment']['cardLast4'] ?? null,
			'receipt_type'     => $parsed['category'] ?? null,
			'items_json'       => $parsed['items'] ?? [],
			'ocr_text'         => $ocrText,
		];
	}

	/**
	 * Нормализовать название магазина (без слоганов)
	 */
	private function normalizeMerchantName(?string $name, string $ocrText): ?string
	{
		$hay = strtolower(($name ?? '') . ' ' . $ocrText);

		$known = [
			'/how\s+doers\s+get\s+more\s+done/i' => 'The Home Depot',
			'/\bhome\s*depot\b/i'                 => 'The Home Depot',
			'/\bwalmart\s+supercentre\b/i'         => 'Walmart Supercentre',
			'/\bwalmart\b/i'                       => 'Walmart',
			'/\bcanadian\s*tire\b/i'               => 'Canadian Tire',
			'/\bcostco\b/i'                        => 'Costco Wholesale',
			'/\bsobeys\b/i'                        => 'Sobeys',
			'/\bfoodland\b/i'                      => 'Foodland',
			'/\bno\s*frills\b/i'                   => 'No Frills',
			'/\besso\b/i'                          => 'Esso',
			'/\bshell\b/i'                         => 'Shell',
			'/\birving\b/i'                        => 'Irving',
			'/\bpetro\s*canada\b/i'                => 'Petro-Canada',
		];

		foreach ($known as $pattern => $normalized) {
			if (preg_match($pattern, $hay)) {
				return $normalized;
			}
		}

		$cleaned = trim($name ?? '', " \t\n\r\0\x0B-—:·|");

		return $cleaned !== '' ? $cleaned : null;
	}

	/**
	 * Нормализовать дату в формат Y-m-d
	 */
	private function normalizeDate(?string $date): ?string
	{
		if (!$date) return null;

		if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $date, $m)) {
			return "{$m[1]}-{$m[2]}-{$m[3]}";
		}

		return null;
	}

	/**
	 * Нормализовать время в формат H:i
	 */
	private function normalizeTime(?string $time): ?string
	{
		if (!$time) return null;

		if (preg_match('/(\d{2}:\d{2})(?::\d{2})?/', $time, $m)) {
			return $m[1];
		}

		return null;
	}

	/**
	 * Нормализовать денежное значение
	 */
	private function normalizeMoney(mixed $value): ?string
	{
		if ($value === null || $value === '') return null;

		$num = (float)preg_replace('/[^0-9.\-]/', '', (string)$value);

		if (!is_finite($num)) return null;

		return number_format($num, 2, '.', '');
	}
}
