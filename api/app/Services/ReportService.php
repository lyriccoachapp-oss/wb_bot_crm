<?php

namespace App\Services;

use App\Repositories\BotPlaceRepository;
use App\Repositories\BotWorktimeRepository;
use App\Repositories\BotReceiptRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Collection;

/**
 * Сервис отчётов
 *
 * Формирует отчёты по объектам, времени и чекам.
 * Поддерживает экспорт в XLSX.
 */
class ReportService
{
	public function __construct(
		private readonly BotPlaceRepository   $placeRepo,
		private readonly BotWorktimeRepository $worktimeRepo,
		private readonly BotReceiptRepository  $receiptRepo
	) {
	}

	/**
	 * Отчёт по объекту: рабочее время + чеки
	 *
	 * @param  int    $placeId
	 * @param  string $dateFrom Y-m-d
	 * @param  string $dateTo   Y-m-d
	 * @return array
	 */
	public function objectReport(int $placeId, string $dateFrom, string $dateTo, array $employeeIds = []): array
	{
		$place      = $this->placeRepo->findById($placeId);
		
		$filters = [
			'place_id' => $placeId,
			'date_from' => $dateFrom,
			'date_to' => $dateTo,
		];
		if (!empty($employeeIds)) {
			$filters['telegram_id'] = $employeeIds;
		}

		$worktimes  = $this->worktimeRepo->getForReport($filters);
		$receipts   = $this->receiptRepo->getForReport($filters);

		// Группируем рабочее время по дням
		$workByDay = $this->groupWorktimeByDay($worktimes);

		// Итого за период
		$totalMinutes = $worktimes->sum(fn ($w) => $w->work_minutes_rounded);
		$totalReceipts = $receipts->sum('receipt_amount');

		return [
			'place'        => $place ? ['id' => $place->id_place, 'name' => $place->place_name] : null,
			'date_from'    => $dateFrom,
			'date_to'      => $dateTo,
			'work_by_day'  => $workByDay,
			'work_total_h' => floor($totalMinutes / 60),
			'work_total_m' => $totalMinutes % 60,
			'receipts'     => $receipts->map(fn ($r) => $this->formatReceipt($r))->values()->toArray(),
			'receipts_total' => round($totalReceipts, 2),
		];
	}

	/**
	 * Создать XLSX файл отчёта по объектам
	 *
	 * @param  int    $placeId
	 * @param  string $dateFrom
	 * @param  string $dateTo
	 * @return string  Путь к временному файлу
	 */
	public function createObjectXlsx(int $placeId, string $dateFrom, string $dateTo, array $employeeIds = []): string
	{
		$data = $this->objectReport($placeId, $dateFrom, $dateTo, $employeeIds);

		$spreadsheet = new Spreadsheet();
		$sheet       = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Рабочее время');

		// Заголовок
		$placeName = $data['place']['name'] ?? "Объект #{$placeId}";
		$sheet->setCellValue('A1', "Отчёт по объекту: {$placeName}");
		$sheet->setCellValue('A2', "Период: {$dateFrom} — {$dateTo}");

		// Заголовки таблицы
		$headers = ['Дата', 'Часы', 'Минуты', 'Сотрудников', 'Сотрудники'];
		$col = 'A';
		$row = 4;

		foreach ($headers as $h) {
			$sheet->setCellValue($col . $row, $h);
			$col++;
		}

		// Стиль заголовков
		$sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
			'font' => ['bold' => true],
			'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a1a2e']],
			'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
		]);

		// Данные
		$row = 5;
		foreach ($data['work_by_day'] as $day) {
			$sheet->setCellValue('A' . $row, $day['date']);
			$sheet->setCellValue('B' . $row, $day['hours']);
			$sheet->setCellValue('C' . $row, $day['minutes']);
			$sheet->setCellValue('D' . $row, count($day['employees']));
			$sheet->setCellValue('E' . $row, implode(', ', $day['employees']));
			$row++;
		}

		// Итого
		$sheet->setCellValue('A' . $row, 'ИТОГО:');
		$sheet->setCellValue('B' . $row, $data['work_total_h']);
		$sheet->setCellValue('C' . $row, $data['work_total_m']);
		$sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);

		// Автоширина колонок
		foreach (range('A', 'E') as $c) {
			$sheet->getColumnDimension($c)->setAutoSize(true);
		}

		// Сохраняем во временный файл
		$tmpPath = sys_get_temp_dir() . '/report_' . uniqid() . '.xlsx';
		(new Xlsx($spreadsheet))->save($tmpPath);

		return $tmpPath;
	}

	/**
	 * Отчёт по сотрудникам: рабочее время + чеки
	 *
	 * @param  int $year
	 * @param  int $month
	 * @param  int $half
	 * @return array
	 */
	public function employeeReport(int $year, int $month, int $half): array
	{
		$month = max(1, min(12, $month));
		$half = $half === 2 ? 2 : 1;
		$startDay = $half === 1 ? 1 : 16;
		$endDay = $half === 1 ? 15 : (int)date('t', strtotime("$year-$month-01"));

		$dateFrom = sprintf('%04d-%02d-%02d', $year, $month, $startDay);
		$dateTo   = sprintf('%04d-%02d-%02d', $year, $month, $endDay);

		$worktimes = $this->worktimeRepo->getForReport(['date_from' => $dateFrom, 'date_to' => $dateTo]);
		$receipts  = $this->receiptRepo->getForReport(['date_from' => $dateFrom, 'date_to' => $dateTo]);

		// Выбираем всех уникальных пользователей из worktimes
		$usersMap = [];
		foreach ($worktimes as $w) {
			$uid = $w->id_telegram;
			if (!isset($usersMap[$uid])) {
				$usersMap[$uid] = [
					'id_telegram' => $uid,
					'name' => $w->botUser ? trim($w->botUser->firstname . ' ' . $w->botUser->lastname) : "ID:{$uid}",
					'worktimes' => [],
					'receipts' => [],
					'total_min' => 0,
					'receipts_total' => 0,
				];
			}
			
			$usersMap[$uid]['worktimes'][] = [
				'id' => $w->id_worktime,
				'date' => $w->workday,
				'place' => $w->place ? $w->place->place_name : ($w->id_place ? "ID:{$w->id_place}" : ''),
				'checkin' => $w->checkin,
				'checkout' => $w->checkout,
				'lunchin' => $w->lunchin,
				'lunchout' => $w->lunchout,
				'hours' => floor($w->work_minutes_rounded / 60),
				'minutes' => $w->work_minutes_rounded % 60,
			];
			$usersMap[$uid]['total_min'] += $w->work_minutes_rounded;
		}

		// Добавляем чеки
		foreach ($receipts as $r) {
			$uid = $r->id_telegram;
			// Если сотрудник есть только в чеках, добавим его (редко, но бывает)
			if (!isset($usersMap[$uid])) {
				$usersMap[$uid] = [
					'id_telegram' => $uid,
					'name' => $r->botUser ? trim($r->botUser->firstname . ' ' . $r->botUser->lastname) : "ID:{$uid}",
					'worktimes' => [],
					'receipts' => [],
					'total_min' => 0,
					'receipts_total' => 0,
				];
			}
			$usersMap[$uid]['receipts'][] = $this->formatReceipt($r);
			$usersMap[$uid]['receipts_total'] += $r->receipt_amount;
		}

		// Форматируем часы для вывода
		$resultUsers = [];
		foreach ($usersMap as $uid => $data) {
			$data['work_total_h'] = floor($data['total_min'] / 60);
			$data['work_total_m'] = $data['total_min'] % 60;
			$data['receipts_total'] = round($data['receipts_total'], 2);
			$resultUsers[] = $data;
		}

		// Сортировка по имени
		usort($resultUsers, fn($a, $b) => strcmp($a['name'], $b['name']));

		return [
			'period' => [
				'year' => $year,
				'month' => $month,
				'half' => $half,
				'date_from' => $dateFrom,
				'date_to' => $dateTo,
			],
			'users' => $resultUsers,
		];
	}

	/**
	 * Создать XLSX файл отчёта по сотрудникам
	 */
	public function createEmployeeXlsx(int $year, int $month, int $half): string
	{
		$data = $this->employeeReport($year, $month, $half);

		$spreadsheet = new Spreadsheet();
		$sheet       = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Отчет');

		$dateFrom = $data['period']['date_from'];
		$dateTo = $data['period']['date_to'];

		$sheet->setCellValue('A1', "Отчёт по сотрудникам: рабочее время и чеки");
		$sheet->setCellValue('A2', "Период: {$dateFrom} — {$dateTo}");

		$row = 4;
		foreach ($data['users'] as $u) {
			// Заголовок сотрудника
			$sheet->setCellValue('A' . $row, $u['name'] . " (ID: {$u['id_telegram']})");
			$sheet->mergeCells("A{$row}:D{$row}");
			
			$sheet->setCellValue('G' . $row, "Итого часов (округл.):");
			$sheet->setCellValue('H' . $row, "{$u['work_total_h']} ч {$u['work_total_m']} м");
			$sheet->mergeCells("H{$row}:I{$row}");

			$sheet->getStyle("A{$row}:I{$row}")->applyFromArray([
				'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'eef2f7']],
				'font' => ['bold' => true],
			]);
			$row++;

			// Таблица Worktime
			if (!empty($u['worktimes'])) {
				$sheet->setCellValue('A' . $row, 'Дата');
				$sheet->setCellValue('B' . $row, 'Объект');
				$sheet->setCellValue('C' . $row, 'Check-in');
				$sheet->setCellValue('D' . $row, 'Check-out');
				$sheet->setCellValue('E' . $row, 'Lunch-in');
				$sheet->setCellValue('F' . $row, 'Lunch-out');
				$sheet->setCellValue('G' . $row, 'Часы');
				$sheet->setCellValue('H' . $row, 'Мин');
				$sheet->getStyle("A{$row}:H{$row}")->getFont()->setBold(true);
				$row++;

				foreach ($u['worktimes'] as $w) {
					$sheet->setCellValue('A' . $row, $w['date']);
					$sheet->setCellValue('B' . $row, $w['place']);
					$sheet->setCellValue('C' . $row, substr((string)$w['checkin'], 0, 19));
					$sheet->setCellValue('D' . $row, substr((string)$w['checkout'], 0, 19));
					$sheet->setCellValue('E' . $row, substr((string)$w['lunchin'], 0, 19));
					$sheet->setCellValue('F' . $row, substr((string)$w['lunchout'], 0, 19));
					$sheet->setCellValue('G' . $row, $w['hours']);
					$sheet->setCellValue('H' . $row, $w['minutes']);
					$row++;
				}
			}

			// Таблица Receipts
			if (!empty($u['receipts'])) {
				$row++;
				$sheet->setCellValue('A' . $row, 'Дата чека');
				$sheet->setCellValue('B' . $row, 'Время');
				$sheet->setCellValue('C' . $row, 'Магазин');
				$sheet->setCellValue('D' . $row, 'Сумма');
				$sheet->setCellValue('E' . $row, 'Категория');
				$sheet->setCellValue('F' . $row, 'Оплата');
				$sheet->setCellValue('G' . $row, 'Карта');
				$sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
				$row++;

				foreach ($u['receipts'] as $r) {
					$sheet->setCellValue('A' . $row, $r['date']);
					$sheet->setCellValue('B' . $row, substr((string)$r['time'], 0, 5));
					$sheet->setCellValue('C' . $row, $r['merchant']);
					$sheet->setCellValue('D' . $row, $r['amount']);
					$sheet->setCellValue('E' . $row, $r['category']);
					$sheet->setCellValue('F' . $row, $r['payment_method']);
					$sheet->setCellValue('G' . $row, $r['card_last4']);
					$row++;
				}
				
				$sheet->setCellValue('C' . $row, 'Итого чеков:');
				$sheet->setCellValue('D' . $row, $u['receipts_total']);
				$sheet->getStyle("C{$row}:D{$row}")->getFont()->setBold(true);
				$row++;
			}

			$row += 2; // отступ между сотрудниками
		}

		foreach (range('A', 'H') as $c) {
			$sheet->getColumnDimension($c)->setAutoSize(true);
		}

		$tmpPath = sys_get_temp_dir() . '/report_emp_' . uniqid() . '.xlsx';
		(new Xlsx($spreadsheet))->save($tmpPath);

		return $tmpPath;
	}

	/**
	 * Сгруппировать записи времени по дням
	 *
	 * @param  Collection $worktimes
	 * @return array
	 */
	private function groupWorktimeByDay(Collection $worktimes): array
	{
		$byDay = [];

		foreach ($worktimes as $w) {
			$date = $w->workday;

			if (!isset($byDay[$date])) {
				$byDay[$date] = [
					'date'        => $date,
					'total_min'   => 0,
					'hours'       => 0,
					'minutes'     => 0,
					'employees'   => [],
					'count'       => 0,
				];
			}

			$mins = $w->work_minutes_rounded;
			$byDay[$date]['total_min'] += $mins;

			$name = $w->botUser
				? trim($w->botUser->firstname . ' ' . $w->botUser->lastname)
				: "ID:{$w->id_telegram}";

			$byDay[$date]['employees'][] = $name;
			$byDay[$date]['count']++;
		}

		// Пересчитываем часы/минуты для каждого дня
		foreach ($byDay as &$day) {
			$day['hours']   = (int)floor($day['total_min'] / 60);
			$day['minutes'] = $day['total_min'] % 60;
		}

		ksort($byDay);

		return array_values($byDay);
	}

	/**
	 * Форматировать чек для ответа API
	 *
	 * @param  mixed $receipt
	 * @return array
	 */
	private function formatReceipt(mixed $receipt): array
	{
		return [
			'id'             => $receipt->id_receipt,
			'date'           => $receipt->receipt_date,
			'time'           => $receipt->receipt_time,
			'merchant'       => $receipt->merchant_name,
			'amount'         => $receipt->receipt_amount,
			'category'       => $receipt->receipt_type,
			'payment_method' => $receipt->payment_method,
			'card_last4'     => $receipt->card_last4,
			'gdrive_url'     => $receipt->gdrive_url,
			'employee'       => $receipt->botUser
				? trim($receipt->botUser->firstname . ' ' . $receipt->botUser->lastname)
				: null,
		];
	}
}
